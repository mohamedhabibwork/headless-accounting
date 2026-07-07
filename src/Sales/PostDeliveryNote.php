<?php

declare(strict_types=1);

namespace Headless\Accounting\Sales;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Support\Config;

/**
 * PostDeliveryNote — translates a {@see DeliveryNote} into:
 *   - one or more consumed cost layers per line (via
 *     {@see InventoryValuationService::issue()})
 *   - one balanced journal entry per line
 *       Dr COGS / Cr Inventory Asset
 *
 * Lines missing `variant_id` or `warehouse_id` are skipped; the legacy
 * aggregate GL entry is preserved as a fallback.
 */
class PostDeliveryNote
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryValuationService $valuation,
        private readonly InventoryPolicyService $policy,
    ) {}

    public function execute(DeliveryNote $note): JournalEntry
    {
        $currency = $note->salesOrder?->currency
            ?? Config::string('headless-accounting.currency.default');

        $companyId = (int) ($note->company_id ?: 1);
        $method = $this->policy->method($companyId);

        $entries = [];
        $total = 0;
        $parsed = 0;

        foreach ((array) $note->lines as $line) {
            $variantId = $line['variant_id'] ?? null;
            $warehouseId = $line['warehouse_id'] ?? $line['location_id'] ?? $note->warehouse_id;
            $quantity = (int) ($line['quantity'] ?? 0);

            if (! $variantId || ! $warehouseId || $quantity <= 0) {
                continue;
            }

            $variant = ProductVariant::query()->find($variantId);
            $warehouse = Location::query()->find($warehouseId);
            if (! $variant || ! $warehouse) {
                continue;
            }

            $consumed = $this->valuation->issue($variant, (int) $warehouse->id, $quantity, $method, $currency);

            $lineCost = 0;
            foreach ($consumed as $row) {
                $lineCost += (int) $row['quantity'] * (int) $row['unit_cost_minor'];
            }

            if ($lineCost > 0) {
                $assetAccount = $this->policy->inventoryAccountFor($variant->product?->item_type);
                $cogsAccount = $this->policy->accountCode('cogs') ?: '5000';

                $entry = $this->journal->post(
                    source: $note,
                    currency: $currency,
                    description: 'Delivery line '.$variantId,
                    autoPosted: true,
                    postings: [
                        ['account' => $cogsAccount, 'debit' => $lineCost, 'memo' => 'COGS'],
                        ['account' => $assetAccount, 'credit' => $lineCost, 'memo' => 'Inventory Asset'],
                    ],
                );

                $entries[] = $entry;
            }

            $total += $lineCost;
            $parsed++;
        }

        if ($parsed === 0) {
            foreach ((array) $note->lines as $line) {
                $total += (int) ($line['amount_minor'] ?? 0);
            }

            $entry = $this->journal->post(
                source: $note,
                currency: $currency,
                description: 'Delivery '.$note->number,
                autoPosted: true,
                postings: [
                    ['account' => $this->policy->accountCode('cogs') ?: '5000', 'debit' => $total, 'memo' => 'COGS'],
                    ['account' => $this->policy->accountCode('inventory') ?: '1400', 'credit' => $total, 'memo' => 'Inventory Asset'],
                ],
            );

            $entries[] = $entry;
        }

        $note->update(['state' => 'posted']);

        return $entries[count($entries) - 1] ?? new JournalEntry;
    }
}
