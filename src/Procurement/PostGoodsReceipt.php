<?php

declare(strict_types=1);

namespace Headless\Accounting\Procurement;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Support\Config;

/**
 * PostGoodsReceipt — translates a {@see GoodsReceipt} into:
 *   - one CostLayer per receipt line (via {@see InventoryValuationService::receipt()})
 *   - an updated StockItem + StockMovement per line
 *   - a single balanced journal entry
 *       Dr Inventory Asset / Cr GRNI
 *
 * Lines missing `variant_id` are skipped; the legacy single GL entry is
 * preserved as a fallback if no line could be parsed.
 */
class PostGoodsReceipt
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryValuationService $valuation,
        private readonly InventoryPolicyService $policy,
    ) {}

    public function execute(GoodsReceipt $receipt): JournalEntry
    {
        $currency = $receipt->vendor?->default_currency
            ?? Config::string('headless-accounting.currency.default');

        $companyId = (int) ($receipt->company_id ?: 1);
        $method = $this->policy->method($companyId);

        $warehouse = Location::query()->find($receipt->warehouse_id);
        $total = 0;
        $parsed = 0;

        foreach ((array) $receipt->lines as $line) {
            $variantId = $line['variant_id'] ?? null;
            $quantity = (int) ($line['quantity'] ?? 0);
            $unitCost = (int) ($line['unit_cost_minor'] ?? 0);
            $lineCurrency = (string) ($line['currency'] ?? $currency);

            if (! $variantId || $quantity <= 0 || $unitCost <= 0 || ! $warehouse) {
                continue;
            }

            $variant = ProductVariant::query()->find($variantId);
            if (! $variant) {
                continue;
            }

            $this->valuation->receipt(
                $variant,
                (int) $warehouse->id,
                $quantity,
                $unitCost,
                $lineCurrency,
                $method,
                $receipt,
            );

            $total += $quantity * $unitCost;
            $parsed++;
        }

        if ($parsed === 0) {
            foreach ((array) $receipt->lines as $line) {
                $total += (int) ($line['amount_minor'] ?? 0);
            }
        }

        $assetAccount = $this->policy->accountCode('inventory') ?: '1400';
        $grniAccount = $this->policy->accountCode('grni') ?: '2010';

        $entry = $this->journal->post(
            source: $receipt,
            currency: $currency,
            description: 'Goods receipt '.$receipt->number,
            autoPosted: true,
            postings: [
                ['account' => $assetAccount, 'debit' => $total, 'memo' => 'Inventory Asset'],
                ['account' => $grniAccount, 'credit' => $total, 'memo' => 'Accounts Payable (GRNI)'],
            ],
        );

        $receipt->update(['state' => 'posted', 'journal_entry_id' => $entry->id]);

        return $entry;
    }
}
