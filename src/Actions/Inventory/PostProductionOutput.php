<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Inventory\InventoryPolicyService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\Bom;
use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductionOrder;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StandardCost;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Support\Config;

/**
 * PostProductionOutput — receives the finished-goods output of a
 * Production Order into stock, opening a cost layer and a balanced
 * journal entry:
 *
 *   Dr Finished Goods (1430) / Cr Work In Progress (1420)
 *
 * Output variant resolution: the BOM's product → its first active
 * variant. If the BOM is missing, the action is a no-op.
 */
final class PostProductionOutput extends Action
{
    public function __construct(
        private readonly Journal $journal,
        private readonly InventoryPolicyService $policy,
        private readonly InventoryValuationService $valuation,
    ) {}

    /**
     * @return array{cost_layer:?CostLayer, stock_movement:?StockMovement, journal_entry:?JournalEntry}
     */
    protected function handle(
        ProductionOrder $productionOrder,
        ?int $outputBinId = null,
        ?string $currency = null,
    ): array {
        $currency ??= Config::string('headless-accounting.currency.default');

        $bom = Bom::query()->find($productionOrder->bom_id);
        $product = $bom ? Product::query()->find($bom->product_id) : null;
        if (! $product) {
            return ['cost_layer' => null, 'stock_movement' => null, 'journal_entry' => null];
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        if (! $variant) {
            return ['cost_layer' => null, 'stock_movement' => null, 'journal_entry' => null];
        }

        $quantityToProduce = max(0, (int) $productionOrder->quantity_to_produce);
        if ($quantityToProduce <= 0) {
            return ['cost_layer' => null, 'stock_movement' => null, 'journal_entry' => null];
        }

        $standardCost = $this->standardUnitCost($variant->id);
        if ($standardCost <= 0) {
            $standardCost = 1;
        }

        $destination = Location::query()->orderBy('id')->first();
        if (! $destination) {
            throw new AccountingException('No default location configured for PostProductionOutput.');
        }

        $companyId = (int) ($productionOrder->company_id ?: 1);
        $method = $this->policy->method($companyId);

        $valuation = $this->valuation;
        $layer = $valuation->receipt($variant, (int) $destination->id, $quantityToProduce, $standardCost, $currency, $method, $productionOrder);

        $movement = StockMovement::query()
            ->where('stock_item_id', function ($q) use ($variant, $destination) {
                $q->select('id')
                    ->from(Config::string('headless-accounting.table_prefix', 'ha_').'stock_items')
                    ->where('variant_id', $variant->id)
                    ->where('location_id', $destination->id)
                    ->limit(1);
            })
            ->orderByDesc('id')
            ->first();

        $totalMinor = (int) $quantityToProduce * $standardCost;
        $wipCode = $this->policy->accountCode('wip') ?: '1420';
        $fgCode = $this->policy->accountCode('finished_goods') ?: '1430';

        $entry = $this->journal->post(
            source: $productionOrder,
            currency: $currency,
            description: 'Production output '.$productionOrder->number,
            autoPosted: true,
            postings: [
                ['account' => $fgCode, 'debit' => $totalMinor, 'memo' => 'Finished Goods'],
                ['account' => $wipCode, 'credit' => $totalMinor, 'memo' => 'Work In Progress'],
            ],
        );

        $productionOrder->update(['journal_entry_id' => $entry->id]);

        return [
            'cost_layer' => $layer instanceof CostLayer ? $layer : null,
            'stock_movement' => $movement,
            'journal_entry' => $entry,
        ];
    }

    private function standardUnitCost(int $variantId): int
    {
        $row = StandardCost::query()
            ->where('variant_id', $variantId)
            ->orderByDesc('effective_from')
            ->first();

        return (int) ($row?->unit_cost_minor ?? 0);
    }
}
