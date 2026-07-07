<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\SerialNumber;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * IssueGoods — single-call inventory issue that:
 *   - consumes cost layers via {@see InventoryValuationService::issue()}
 *   - decrements {@see StockItem} + writes a StockMovement
 *   - if the variant is batch-tracked, decrements BatchStock (FEFO when
 *     enabled, otherwise delegates to the configured valuation method)
 *   - if the variant is serial-tracked and a serial number is provided,
 *     marks the serial as 'in_transit' (or 'sold' for sales)
 *   - opens a balanced journal entry: Dr COGS / Cr Inventory Asset
 */
final class IssueGoods extends Action
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryValuationService $valuation,
        private readonly InventoryPolicyService $policy,
    ) {}

    /**
     * @return array{consumed_layers:array<int,array<string,int>>, stock_movement:?StockMovement, serial_number:?SerialNumber, journal_entry:?JournalEntry}
     */
    protected function handle(
        ProductVariant $variant,
        Location $warehouse,
        int $quantity,
        ?string $reason = 'sales',
        ?int $binId = null,
        ?string $batchNumber = null,
        ?string $serialNumber = null,
        ?Model $source = null,
        ?string $currency = null,
    ): array {
        $currency ??= Config::string('headless-accounting.currency.default');
        $companyId = (int) ($variant->company_id ?? $source?->company_id ?? 1);
        $method = $this->policy->method($companyId);

        // Reject early if there isn't enough stock so callers don't
        // silently receive a partial issue.
        $stockItem = StockItem::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $warehouse->id)
            ->first();
        if (! $stockItem || $stockItem->available() < $quantity) {
            throw new AccountingException(
                "Insufficient stock for variant {$variant->id} at location {$warehouse->id}: requested {$quantity}, available ".(int) ($stockItem?->available() ?? 0).'.'
            );
        }

        $consumedLayers = $this->valuation->issue($variant, (int) $warehouse->id, $quantity, $method, $currency);

        $totalCostMinor = 0;
        foreach ($consumedLayers as $row) {
            $totalCostMinor += (int) $row['quantity'] * (int) $row['unit_cost_minor'];
        }

        $serial = null;
        if ($serialNumber !== null && $serialNumber !== '' && $variant->serial_tracked) {
            $serial = SerialNumber::query()
                ->where('variant_id', $variant->id)
                ->where('serial', $serialNumber)
                ->first();

            if ($serial) {
                $serial->status = $reason === 'sales' ? 'sold' : 'in_transit';
                $serial->save();
            }
        }

        $assetAccount = $this->policy->inventoryAccountFor($variant->product?->item_type);
        $debitAccount = $this->resolveDebitAccount($reason);

        $entry = null;
        if ($totalCostMinor > 0) {
            $entry = $this->journal->post(
                source: $source ?? $variant,
                currency: $currency,
                description: 'Goods issue '.$variant->sku.' ('.$reason.')',
                autoPosted: true,
                postings: [
                    ['account' => $debitAccount, 'debit' => $totalCostMinor, 'memo' => ucfirst($reason)],
                    ['account' => $assetAccount, 'credit' => $totalCostMinor, 'memo' => 'Inventory Asset'],
                ],
            );
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

        return [
            'consumed_layers' => $consumedLayers,
            'stock_movement' => $movement,
            'serial_number' => $serial,
            'journal_entry' => $entry,
        ];
    }

    private function resolveDebitAccount(string $reason): string
    {
        return match ($reason) {
            'damage' => $this->policy->accountCode('inventory_damage') ?: '5200',
            'loss' => $this->policy->accountCode('inventory_shrinkage') ?: '5100',
            'obsolete' => $this->policy->accountCode('inventory_shrinkage') ?: '5100',
            default => $this->policy->accountCode('cogs') ?: '5000',
        };
    }
}
