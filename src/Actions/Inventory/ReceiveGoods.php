<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Carbon\Carbon;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Inventory\BatchService;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * ReceiveGoods — one-call inventory receipt that:
 *   - records a {@see CostLayer} (via {@see InventoryValuationService::receipt()})
 *   - updates {@see StockItem} and writes a StockMovement
 *   - if a batch number is given, creates/finds the Batch + BatchStock
 *   - opens a balanced journal entry
 *       Dr Inventory Asset / Cr GRNI (Goods Received Not Invoiced)
 *
 * The asset account is selected per product item type:
 *   'raw_material' → inventory_raw
 *   'finished_good' → finished_goods
 *   'semi_finished' → wip
 *   other / unset → inventory (1400)
 */
final class ReceiveGoods extends Action
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryValuationService $valuation,
        private readonly BatchService $batches,
        private readonly InventoryPolicyService $policy,
    ) {}

    /**
     * @return array{cost_layer:CostLayer, stock_movement:?StockMovement, batch:?Batch, journal_entry:?JournalEntry}
     */
    protected function handle(
        ProductVariant $variant,
        Location $warehouse,
        int $quantity,
        int $unitCostMinor,
        string $currency,
        ?string $batchNumber = null,
        ?Carbon $manufacturingDate = null,
        ?Carbon $expirationDate = null,
        ?int $binId = null,
        ?Model $source = null,
        ?int $companyId = null,
    ): array {
        $companyId ??= (int) ($variant->company_id ?? $source?->company_id ?? 1);
        $method = $this->policy->method($companyId);

        $layer = $this->valuation->receipt(
            $variant,
            (int) $warehouse->id,
            $quantity,
            $unitCostMinor,
            $currency,
            $method,
            $source,
        );

        $batch = null;
        if ($batchNumber !== null && $batchNumber !== '') {
            $batch = $this->batches->create(
                (int) $variant->id,
                $batchNumber,
                $manufacturingDate,
                $expirationDate,
            );

            if ($binId !== null) {
                $this->batches->receive($batch, (int) $warehouse->id, $binId, $quantity, $unitCostMinor, $currency);
            }
        }

        $movement = StockMovement::query()
            ->where('stock_item_id', function ($q) use ($variant, $warehouse) {
                $q->select('id')
                    ->from(Config::string('headless-accounting.table_prefix', 'ha_').'stock_items')
                    ->where('variant_id', $variant->id)
                    ->where('location_id', $warehouse->id)
                    ->limit(1);
            })
            ->orderByDesc('id')
            ->first();

        $itemType = $variant->product?->item_type;
        $assetAccount = $this->policy->inventoryAccountFor($itemType);
        $grniAccount = $this->policy->accountCode('grni') ?: '2010';

        $totalMinor = (int) $quantity * (int) $unitCostMinor;

        $entry = $this->journal->post(
            source: $source ?? $variant,
            currency: $currency,
            description: 'Goods receipt '.$variant->sku,
            autoPosted: true,
            postings: [
                ['account' => $assetAccount, 'debit' => $totalMinor, 'memo' => 'Inventory Asset'],
                ['account' => $grniAccount, 'credit' => $totalMinor, 'memo' => 'GRNI'],
            ],
        );

        return [
            'cost_layer' => $layer,
            'stock_movement' => $movement,
            'batch' => $batch,
            'journal_entry' => $entry,
        ];
    }
}
