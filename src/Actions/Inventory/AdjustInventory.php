<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\InventoryAdjustment;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * AdjustInventory — applies a manual stock-count delta and posts the
 * corresponding balanced journal entry.
 *
 * `$reason` ∈ {gain, loss, damage, obsolete, count}
 *
 *   gain      → Dr Inventory Asset / Cr Inventory Gain (4400)
 *   loss      → Dr Inventory Shrinkage (5100) / Cr Inventory Asset
 *   damage    → Dr Inventory Damage (5200) / Cr Inventory Asset
 *   obsolete  → Dr Inventory Shrinkage (5100) / Cr Inventory Asset
 *   count     → Dr Inventory Shrinkage (5100) / Cr Inventory Asset
 *     (positive delta on 'count' is treated as gain)
 */
final class AdjustInventory extends Action
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryValuationService $valuation,
        private readonly InventoryPolicyService $policy,
    ) {}

    /**
     * @return array{inventory_adjustment:InventoryAdjustment, stock_movement:?StockMovement, journal_entry:?JournalEntry}
     */
    protected function handle(
        ProductVariant $variant,
        Location $warehouse,
        int $quantityDelta,
        string $reason,
        ?int $binId = null,
        ?string $batchNumber = null,
        ?Model $source = null,
        ?string $currency = null,
        ?int $unitCostMinor = null,
    ): array {
        $currency ??= Config::string('headless-accounting.currency.default');
        $companyId = (int) ($variant->company_id ?? $source?->company_id ?? 1);
        $method = $this->policy->method($companyId);

        $assetAccount = $this->policy->inventoryAccountFor($variant->product?->item_type);

        $totalMinor = 0;

        if ($quantityDelta > 0) {
            $unitCost = $unitCostMinor ?? 0;
            $this->valuation->receipt(
                $variant,
                (int) $warehouse->id,
                $quantityDelta,
                $unitCost,
                $currency,
                $method,
                $source,
            );
            $totalMinor = $unitCost * $quantityDelta;
        } elseif ($quantityDelta < 0) {
            $consumed = $this->valuation->issue(
                $variant,
                (int) $warehouse->id,
                abs($quantityDelta),
                $method,
                $currency,
            );
            foreach ($consumed as $row) {
                $totalMinor += (int) $row['quantity'] * (int) $row['unit_cost_minor'];
            }
        }

        $normalizedReason = $this->normalizeReason($reason, $quantityDelta);
        $contraAccount = $this->contraAccountFor($normalizedReason);

        $entry = null;
        if ($totalMinor > 0) {
            $isGain = $normalizedReason === 'gain';
            $entry = $this->journal->post(
                source: $source ?? $variant,
                currency: $currency,
                description: 'Inventory adjustment ('.$reason.') '.$variant->sku,
                autoPosted: true,
                postings: $isGain
                    ? [
                        ['account' => $assetAccount, 'debit' => $totalMinor, 'memo' => 'Inventory Asset'],
                        ['account' => $contraAccount, 'credit' => $totalMinor, 'memo' => 'Inventory Gain'],
                    ]
                    : [
                        ['account' => $contraAccount, 'debit' => $totalMinor, 'memo' => 'Inventory '.$normalizedReason],
                        ['account' => $assetAccount, 'credit' => $totalMinor, 'memo' => 'Inventory Asset'],
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

        $adjustment = InventoryAdjustment::create([
            'company_id' => $companyId,
            'number' => $this->nextNumber(),
            'location_id' => $warehouse->id,
            'adjusted_at' => now()->toDateString(),
            'reason' => $reason.':'.$variant->sku,
            'lines' => [[
                'variant_id' => $variant->id,
                'quantity_delta' => $quantityDelta,
                'unit_cost_minor' => $unitCostMinor,
                'total_minor' => $totalMinor,
                'reason' => $normalizedReason,
                'bin_id' => $binId,
                'batch_number' => $batchNumber,
            ]],
            'notes' => $source ? 'Source: '.$source->getMorphClass().':'.$source->getKey() : null,
            'journal_entry_id' => $entry?->id,
        ]);

        return [
            'inventory_adjustment' => $adjustment,
            'stock_movement' => $movement,
            'journal_entry' => $entry,
        ];
    }

    private function normalizeReason(string $reason, int $delta): string
    {
        if ($reason === 'count') {
            return $delta >= 0 ? 'gain' : 'loss';
        }

        return in_array($reason, ['gain', 'loss', 'damage', 'obsolete'], true)
            ? $reason
            : 'loss';
    }

    private function contraAccountFor(string $normalizedReason): string
    {
        return match ($normalizedReason) {
            'gain' => $this->policy->accountCode('inventory_gain') ?: '4400',
            'damage' => $this->policy->accountCode('inventory_damage') ?: '5200',
            default => $this->policy->accountCode('inventory_shrinkage') ?: '5100',
        };
    }

    private function nextNumber(): string
    {
        $prefix = Config::string('headless-accounting.number_prefixes.inventory_adjustment', 'ADJ');

        return sprintf(
            '%s-%s-%05d',
            $prefix,
            now()->format('Ymd'),
            (int) (InventoryAdjustment::query()->whereDate('created_at', today())->count()) + 1,
        );
    }
}
