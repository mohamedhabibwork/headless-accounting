<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\InventoryTransfer;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * TransferStock — moves stock between two locations (warehouses):
 *   - issues from source (consumes cost layers, no GL by default)
 *   - receives at destination (new CostLayer at the consumed unit cost)
 *   - writes 'transfer-out' / 'transfer-in' StockMovement rows
 *   - persists an InventoryTransfer document with state='posted'
 *   - if the two warehouses belong to different companies OR the
 *     destination is inter-company / consignment / virtual, also opens
 *     a balanced inter-company journal entry
 */
final class TransferStock extends Action
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryValuationService $valuation,
        private readonly InventoryPolicyService $policy,
    ) {}

    /**
     * @return array{stock_movement_out:?StockMovement, stock_movement_in:?StockMovement, cost_layer:?CostLayer, inventory_transfer:InventoryTransfer, journal_entry:?JournalEntry}
     */
    protected function handle(
        ProductVariant $variant,
        Location $from,
        Location $to,
        int $quantity,
        ?int $fromBinId = null,
        ?int $toBinId = null,
        ?string $batchNumber = null,
        ?Model $source = null,
        ?string $currency = null,
    ): array {
        $currency ??= Config::string('headless-accounting.currency.default');
        $companyId = (int) ($variant->company_id ?? $source?->company_id ?? 1);
        $method = $this->policy->method($companyId);

        $consumedLayers = $this->valuation->issue($variant, (int) $from->id, $quantity, $method, $currency);

        $unitCost = 0;
        $totalCostMinor = 0;
        foreach ($consumedLayers as $row) {
            $qty = (int) $row['quantity'];
            $cost = (int) $row['unit_cost_minor'];
            $totalCostMinor += $qty * $cost;
        }
        if ($totalCostMinor > 0 && $quantity > 0) {
            $unitCost = (int) round($totalCostMinor / $quantity);
        }

        $newLayer = $this->valuation->receipt(
            $variant,
            (int) $to->id,
            $quantity,
            $unitCost,
            $currency,
            $method,
            $source,
        );

        $transfer = InventoryTransfer::create([
            'company_id' => $companyId,
            'number' => $this->nextNumber('inventory_transfer'),
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'transferred_at' => now()->toDateString(),
            'state' => 'posted',
            'lines' => [[
                'variant_id' => $variant->id,
                'quantity' => $quantity,
                'unit_cost_minor' => $unitCost,
                'batch_number' => $batchNumber,
                'from_bin_id' => $fromBinId,
                'to_bin_id' => $toBinId,
            ]],
        ]);

        $stockOut = $this->latestMovementFor($variant, $from);
        $stockIn = $this->latestMovementFor($variant, $to);

        $entry = null;
        $fromWarehouse = $this->warehouseFor($from);
        $toWarehouse = $this->warehouseFor($to);

        $isInterCompany = ($fromWarehouse?->owner_company_id ?? null) !== ($toWarehouse?->owner_company_id ?? null)
            || (bool) ($toWarehouse?->inter_company ?? false)
            || (bool) ($toWarehouse?->consignment ?? false)
            || (bool) ($toWarehouse?->virtual ?? false);

        if ($isInterCompany && $totalCostMinor > 0) {
            $assetCode = $this->policy->inventoryAccountFor($variant->product?->item_type);

            $entry = $this->journal->post(
                source: $transfer,
                currency: $currency,
                description: 'Inter-company transfer '.$transfer->number,
                autoPosted: true,
                postings: [
                    ['account' => $assetCode, 'debit' => $totalCostMinor, 'memo' => 'Inventory (destination)'],
                    ['account' => $assetCode, 'credit' => $totalCostMinor, 'memo' => 'Inventory (source)'],
                ],
            );

            $transfer->update(['journal_entry_id' => $entry->id]);
        }

        return [
            'stock_movement_out' => $stockOut,
            'stock_movement_in' => $stockIn,
            'cost_layer' => $newLayer,
            'inventory_transfer' => $transfer,
            'journal_entry' => $entry,
        ];
    }

    private function nextNumber(string $key): string
    {
        $prefix = Config::string('headless-accounting.number_prefixes.'.$key, 'TR');

        return sprintf(
            '%s-%s-%05d',
            $prefix,
            now()->format('Ymd'),
            (int) (InventoryTransfer::query()->whereDate('created_at', today())->count()) + 1,
        );
    }

    private function latestMovementFor(ProductVariant $variant, Location $warehouse): ?StockMovement
    {
        return StockMovement::query()
            ->where('stock_item_id', function ($q) use ($variant, $warehouse) {
                $q->select('id')
                    ->from(Config::string('headless-accounting.table_prefix', 'ha_').'stock_items')
                    ->where('variant_id', $variant->id)
                    ->where('location_id', $warehouse->id)
                    ->limit(1);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function warehouseFor(Location $location): ?Warehouse
    {
        return Warehouse::query()
            ->where('location_id', $location->id)
            ->first();
    }
}
