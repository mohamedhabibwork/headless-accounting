<?php

declare(strict_types=1);

namespace Headless\Accounting\Inventory;

use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * InventoryValuationService — main entry point for inventory
 * valuation. Reads the company's accounting policy for the chosen
 * method and dispatches to {@see CostMethods}.
 */
class InventoryValuationService
{
    public function __construct(private readonly CostMethods $methods) {}

    /**
     * Compute the inventory valuation at `$asOf` for the entire
     * company. Returns a flat list grouped by variant.
     */
    public function valuationAsOf(int $companyId, ?string $asOf = null, ?string $currency = null): array
    {
        $asOf ??= now()->toDateString();
        $currency ??= Config::string('headless-accounting.currency.default');

        return CostLayer::query()
            ->where('company_id', $companyId)
            ->where('currency', $currency)
            ->selectRaw('variant_id, SUM(quantity_remaining * unit_cost_minor) as valuation_minor')
            ->groupBy('variant_id')
            ->get()
            ->map(fn ($row) => ['variant_id' => $row->variant_id, 'valuation_minor' => (int) $row->valuation_minor])
            ->all();
    }

    public function issue(ProductVariant $variant, int $locationId, int $qty, string $method, ?string $currency = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');
        $consumed = $this->methods->consumeForIssue($variant, $locationId, $qty, $method);

        // Update StockItem.
        $stock = StockItem::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $locationId)
            ->first();
        if ($stock) {
            $stock->on_hand = max(0, (int) $stock->on_hand - $qty);
            $stock->save();
            StockMovement::create([
                'stock_item_id' => $stock->id,
                'reason' => 'consume',
                'quantity' => -$qty,
                'balance_after' => $stock->on_hand,
                'source_type' => $variant->getMorphClass(),
                'source_id' => $variant->id,
                'occurred_at' => now(),
            ]);
        }

        return $consumed;
    }

    public function receipt(ProductVariant $variant, int $locationId, int $qty, int $unitCostMinor, string $currency, string $method = 'fifo', ?Model $source = null): CostLayer
    {
        $layer = $this->methods->recordReceipt($variant, $locationId, $qty, $unitCostMinor, $currency, $source, $method);

        $stock = StockItem::firstOrCreate(
            ['variant_id' => $variant->id, 'location_id' => $locationId],
            ['on_hand' => 0, 'reserved' => 0, 'incoming' => 0],
        );
        $stock->on_hand += $qty;
        $stock->save();

        // Use the provided source, or fall back to the variant itself
        // so the morph column stays NOT NULL.
        $sourceType = $source?->getMorphClass() ?? $variant->getMorphClass();
        $sourceId = $source?->getKey() ?? $variant->getKey();

        StockMovement::create([
            'stock_item_id' => $stock->id,
            'reason' => 'receipt',
            'quantity' => $qty,
            'balance_after' => $stock->on_hand,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'occurred_at' => now(),
        ]);

        return $layer;
    }
}
