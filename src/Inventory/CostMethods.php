<?php

declare(strict_types=1);

namespace Headless\Accounting\Inventory;

use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StandardCost;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * CostMethods — pluggable inventory valuation strategies.
 * The applicable method is stored per company under key
 * `accounting_policies.inventory_valuation_method` and looked up by
 * {@see InventoryValuationService}.
 */
final class CostMethods
{
    public const METHOD_FIFO = 'fifo';

    public const METHOD_LIFO = 'lifo';

    public const METHOD_WEIGHTED = 'weighted_average';

    public const METHOD_STANDARD = 'standard';

    /**
     * Computes the unit cost and the per-line consumed layers for a
     * "use" of `$qty` units of `$variant` from `$location`. Returns
     * an array of 'consumed layer' rows that callers persist into
     * a CostLayer row whose quantity_received/quantity_remaining
     * are reversed.
     *
     *   $consumed = (new CostMethods)->consumeForIssue($variant, $location, $qty);
     *   // [['cost_layer_id' => 1, 'quantity' => 4, 'unit_cost_minor' => 250], ...]
     *
     * @return array<int, array{cost_layer_id:int,quantity:int,unit_cost_minor:int}>
     */
    public function consumeForIssue(ProductVariant $variant, int $locationId, int $qty, string $method): array
    {
        return match ($method) {
            self::METHOD_FIFO => $this->consumeFifo($variant, $locationId, $qty),
            self::METHOD_LIFO => $this->consumeLifo($variant, $locationId, $qty),
            self::METHOD_WEIGHTED => $this->consumeWeighted($variant, $locationId, $qty),
            self::METHOD_STANDARD => $this->consumeStandard($variant, $qty),
            default => throw new InvalidArgumentException("Unknown cost method: {$method}"),
        };
    }

    /** @return array<int, array{cost_layer_id:int,quantity:int,unit_cost_minor:int}> */
    private function consumeFifo(ProductVariant $variant, int $locationId, int $qty): array
    {
        $remaining = $qty;
        $out = [];
        $layers = CostLayer::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $locationId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')            // FIFO
            ->orderBy('id')
            ->get();
        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $layer->quantity_remaining);
            $layer->quantity_remaining -= $take;
            $layer->save();
            $out[] = ['cost_layer_id' => $layer->id, 'quantity' => $take, 'unit_cost_minor' => (int) $layer->unit_cost_minor];
            $remaining -= $take;
        }

        return $out;
    }

    private function consumeLifo(ProductVariant $variant, int $locationId, int $qty): array
    {
        $remaining = $qty;
        $out = [];
        $layers = CostLayer::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $locationId)
            ->where('quantity_remaining', '>', 0)
            ->orderByDesc('received_at')         // LIFO
            ->orderByDesc('id')
            ->get();
        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $layer->quantity_remaining);
            $layer->quantity_remaining -= $take;
            $layer->save();
            $out[] = ['cost_layer_id' => $layer->id, 'quantity' => $take, 'unit_cost_minor' => (int) $layer->unit_cost_minor];
            $remaining -= $take;
        }

        return $out;
    }

    private function consumeWeighted(ProductVariant $variant, int $locationId, int $qty): array
    {
        $totalQty = (int) CostLayer::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $locationId)
            ->sum('quantity_remaining');
        $totalCost = (int) CostLayer::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $locationId)
            ->selectRaw('SUM(quantity_remaining * unit_cost_minor) as total')->value('total');
        $unitCost = (int) ($totalCost > 0 && $totalQty > 0 ? round($totalCost / $totalQty) : 0);

        $out = [['cost_layer_id' => 0, 'quantity' => $qty, 'unit_cost_minor' => $unitCost]];

        // Recompute running WA on each remaining layer proportionally.
        $layers = CostLayer::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $locationId)
            ->where('quantity_remaining', '>', 0)
            ->get();
        foreach ($layers as $layer) {
            $layer->unit_cost_minor = $unitCost;
            $layer->save();
        }

        return $out;
    }

    private function consumeStandard(ProductVariant $variant, int $qty): array
    {
        $standard = StandardCost::query()
            ->where('variant_id', $variant->id)
            ->orderByDesc('effective_from')
            ->first();
        $cost = (int) ($standard->unit_cost_minor ?? 0);

        return [['cost_layer_id' => 0, 'quantity' => $qty, 'unit_cost_minor' => $cost]];
    }

    /** Records a new cost layer on receipt. */
    public function recordReceipt(
        ProductVariant $variant,
        int $locationId,
        int $qty,
        int $unitCostMinor,
        string $currency,
        ?Model $source = null,
        string $method = 'fifo',
    ): CostLayer {
        CostLayer::create([
            'company_id' => $variant->company_id ?? $source?->company_id ?? 1,
            'variant_id' => $variant->id,
            'location_id' => $locationId,
            'received_at' => now()->toDateString(),
            'quantity_received' => $qty,
            'quantity_remaining' => $qty,
            'unit_cost_minor' => $unitCostMinor,
            'currency' => $currency,
            'source' => 'gr',
            'source_document_type' => $source?->getMorphClass() ?? $variant->getMorphClass(),
            'source_document_id' => $source?->getKey() ?? $variant->getKey(),
        ]);

        if ($method === self::METHOD_WEIGHTED) {
            $this->recalculateWeightedAverage($variant, $locationId);
        }

        return CostLayer::query()->latest('id')->first();
    }

    private function recalculateWeightedAverage(ProductVariant $variant, int $locationId): void
    {
        $totalQty = (int) CostLayer::query()
            ->where('variant_id', $variant->id)->where('location_id', $locationId)
            ->sum('quantity_remaining');
        $totalCost = (int) CostLayer::query()
            ->where('variant_id', $variant->id)->where('location_id', $locationId)
            ->selectRaw('SUM(quantity_remaining * unit_cost_minor) as total')->value('total');
        $avg = $totalQty > 0 ? (int) round($totalCost / $totalQty) : 0;

        CostLayer::query()
            ->where('variant_id', $variant->id)->where('location_id', $locationId)
            ->update(['unit_cost_minor' => $avg]);
    }
}
