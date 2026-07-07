<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Models\Bom;
use Headless\Accounting\Models\BomLine;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductionOrder;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Support\Config;

/**
 * MaterialIssue — production-floor component consumption. For every
 * line of the production order's active BOM it issues
 * `line.quantity × quantity_to_produce × (1 + scrap_pct/100)` units to
 * the production location (defaulting to the BOM's warehouse) and
 * posts the net journal entry:
 *
 *   Dr Work In Progress (1420) / Cr Inventory Asset (1400 / per item-type)
 *
 * If the BOM or its lines are missing, the action is a no-op and
 * returns an empty breakdown.
 */
final class MaterialIssue extends Action
{
    public function __construct(
        private readonly Journal $journal,
        private readonly IssueGoods $issue,
        private readonly InventoryPolicyService $policy,
    ) {}

    /**
     * @return array{consumed:array<int,array<string,int>>, journal_entry:?JournalEntry}
     */
    protected function handle(ProductionOrder $productionOrder, ?string $currency = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');
        $bom = Bom::query()->find($productionOrder->bom_id);
        if (! $bom) {
            return ['consumed' => [], 'journal_entry' => null];
        }

        $lines = BomLine::query()->where('bom_id', $bom->id)->get();
        if ($lines->isEmpty()) {
            return ['consumed' => [], 'journal_entry' => null];
        }

        $quantityToProduce = max(1, (int) $productionOrder->quantity_to_produce);
        $consumed = [];
        $totalMinor = 0;
        $destination = $this->resolveProductionLocation($productionOrder);

        foreach ($lines as $line) {
            $component = Product::query()->find($line->component_id);
            if (! $component) {
                continue;
            }

            $variant = $this->firstVariantFor($component);
            if (! $variant) {
                continue;
            }

            $qtyPerUnit = (int) $line->quantity;
            $scrapPct = (float) ($line->scrap_pct ?? 0);
            $qty = (int) ceil($qtyPerUnit * $quantityToProduce * (1 + $scrapPct / 100));

            $result = $this->issue->execute(
                $variant,
                $destination,
                $qty,
                'production',
                null,
                null,
                null,
                $productionOrder,
                $currency,
            );

            $layers = $result['consumed_layers'] ?? [];
            foreach ($layers as $row) {
                $minor = (int) $row['quantity'] * (int) $row['unit_cost_minor'];
                $totalMinor += $minor;
                $consumed[] = [
                    'component_id' => $component->id,
                    'variant_id' => $variant->id,
                    'quantity' => (int) $row['quantity'],
                    'unit_cost_minor' => (int) $row['unit_cost_minor'],
                    'total_minor' => $minor,
                ];
            }
        }

        $entry = null;
        if ($totalMinor > 0) {
            $assetCode = $this->policy->inventoryAccountFor(null);
            $wipCode = $this->policy->accountCode('wip') ?: '1420';

            $entry = $this->journal->post(
                source: $productionOrder,
                currency: $currency,
                description: 'Production components '.$productionOrder->number,
                autoPosted: true,
                postings: [
                    ['account' => $wipCode, 'debit' => $totalMinor, 'memo' => 'Work In Progress'],
                    ['account' => $assetCode, 'credit' => $totalMinor, 'memo' => 'Inventory Asset'],
                ],
            );
        }

        return [
            'consumed' => $consumed,
            'journal_entry' => $entry,
        ];
    }

    private function firstVariantFor(Product $product): ?ProductVariant
    {
        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('active', true)
            ->orderBy('id')
            ->first();
    }

    private function resolveProductionLocation(ProductionOrder $productionOrder): Location
    {
        $location = Location::query()->orderBy('id')->first();

        if (! $location) {
            throw new AccountingException('No default location configured for MaterialIssue.');
        }

        return $location;
    }
}
