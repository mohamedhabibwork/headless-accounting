<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockWriteOff;
use Headless\Accounting\Support\Config;

/**
 * PostWriteOff — finalises a {@see StockWriteOff} by issuing each line
 * and posting a balanced journal entry to the appropriate loss / damage
 * account.
 *
 *   lost|obsolete|expired    → Inventory Shrinkage (5100)
 *   damaged|stolen|recalled  → Inventory Damage   (5200)
 */
final class PostWriteOff extends Action
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryPolicyService $policy,
        private readonly InventoryValuationService $valuation,
    ) {}

    /**
     * @return array{adjusted_stock_items:array<int,StockItem>, journal_entry:?JournalEntry}
     */
    protected function handle(StockWriteOff $writeOff, ?string $currency = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');

        if ($writeOff->state === 'disposed' || $writeOff->state === 'cancelled') {
            throw new AccountingException(
                "Write-off {$writeOff->number} is already in state '{$writeOff->state}'."
            );
        }

        $companyId = (int) ($writeOff->company_id ?: 1);
        $method = $this->policy->method($companyId);

        $totalMinor = 0;
        $lossAccount = $this->resolveLossAccount($writeOff);
        $assetAccount = $this->policy->accountCode('inventory') ?: '1400';
        $adjustedItems = [];

        $lines = (array) $writeOff->lines;
        foreach ($lines as $line) {
            $variantId = $line['variant_id'] ?? null;
            $quantity = (int) ($line['quantity'] ?? 0);
            if (! $variantId || $quantity <= 0) {
                continue;
            }

            $variant = ProductVariant::query()->find($variantId);
            if (! $variant) {
                continue;
            }

            $warehouse = Location::query()->find($writeOff->warehouse_id);
            if (! $warehouse) {
                continue;
            }

            $consumed = $this->valuation->issue($variant, (int) $warehouse->id, $quantity, $method, $currency);

            foreach ($consumed as $row) {
                $totalMinor += (int) $row['quantity'] * (int) $row['unit_cost_minor'];
            }

            $item = StockItem::query()
                ->where('variant_id', $variantId)
                ->where('location_id', $writeOff->warehouse_id)
                ->first();

            if ($item) {
                $adjustedItems[] = $item;
            }
        }

        $entry = null;
        if ($totalMinor > 0) {
            $entry = $this->journal->post(
                source: $writeOff,
                currency: $currency,
                description: 'Write-off '.$writeOff->number,
                autoPosted: true,
                postings: [
                    ['account' => $lossAccount, 'debit' => $totalMinor, 'memo' => 'Inventory '.$writeOff->category],
                    ['account' => $assetAccount, 'credit' => $totalMinor, 'memo' => 'Inventory Asset'],
                ],
            );
        }

        $writeOff->update([
            'state' => 'disposed',
            'journal_entry_id' => $entry?->id,
        ]);

        return [
            'adjusted_stock_items' => $adjustedItems,
            'journal_entry' => $entry,
        ];
    }

    private function resolveLossAccount(StockWriteOff $writeOff): string
    {
        $damagedKeys = ['damaged', 'stolen', 'recalled'];

        return in_array($writeOff->category, $damagedKeys, true)
            ? ($this->policy->accountCode('inventory_damage') ?: '5200')
            : ($this->policy->accountCode('inventory_shrinkage') ?: '5100');
    }
}
