<?php

declare(strict_types=1);

namespace Headless\Accounting\Fulfillment;

use Headless\Accounting\Actions\Fulfillment\CreateFulfillmentPlan;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;
use InvalidArgumentException;

/**
 * AllocationEngine — splits an order's lines across warehouses according
 * to a chosen strategy:
 *
 *  - cheapest   : lowest landed shipping cost (requires rate cards)
 *  - fastest    : shortest ETA (requires rate cards)
 *  - closest    : nearest warehouse by great-circle distance to ship-to
 *  - priority   : warehouse.priority ascending
 *  - manual     : caller passes pre-built allocations
 *
 * The result is an array of
 *   ['warehouse_id' => int, 'variant_id' => int, 'quantity' => int,
 *    'bin_id' => ?int, 'weight_grams' => int]
 * suitable for {@see CreateFulfillmentPlan}.
 */
class AllocationEngine
{
    /**
     * @param  array<int, array{variant_id:int, quantity:int, weight_grams?:int}>  $lines
     * @return array<int, array<string,mixed>>
     */
    public function allocate(
        Order $order,
        array $lines,
        string $strategy = FulfillmentPlan::STRATEGY_PRIORITY,
        ?Warehouse $preferredWarehouse = null,
    ): array {
        return match ($strategy) {
            FulfillmentPlan::STRATEGY_CHEAPEST => $this->allocateByCheapestShipping($order, $lines),
            FulfillmentPlan::STRATEGY_FASTEST => $this->allocateByFastestShipping($order, $lines),
            FulfillmentPlan::STRATEGY_CLOSEST => $this->allocateByProximity($order, $lines),
            FulfillmentPlan::STRATEGY_MANUAL => $this->allocateManual($order, $lines, $preferredWarehouse),
            FulfillmentPlan::STRATEGY_PRIORITY => $this->allocateByWarehousePriority($lines),
            default => throw new InvalidArgumentException("Unknown allocation strategy: {$strategy}"),
        };
    }

    /** @param array<int, array{variant_id:int, quantity:int, weight_grams?:int}> $lines */
    public function allocateByWarehousePriority(array $lines): array
    {
        $warehouses = Warehouse::query()
            ->where('fulfillment_enabled', true)
            ->where('active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return $this->split($lines, $warehouses);
    }

    /** @param array<int, array{variant_id:int, quantity:int, weight_grams?:int}> $lines */
    public function allocateByProximity(Order $order, array $lines): array
    {
        $lat = (float) data_get($order->shipping_address_snapshot, 'latitude', 0);
        $lng = (float) data_get($order->shipping_address_snapshot, 'longitude', 0);

        $warehouses = Warehouse::query()
            ->where('fulfillment_enabled', true)
            ->where('active', true)
            ->whereNotNull('latitude')
            ->get()
            ->sortBy(fn (Warehouse $w) => $w->distanceKmFrom($lat, $lng) ?? PHP_FLOAT_MAX)
            ->values();

        return $this->split($lines, $warehouses);
    }

    /** @param array<int, array{variant_id:int, quantity:int, weight_grams?:int}> $lines */
    public function allocateByCheapestShipping(Order $order, array $lines): array
    {
        $country = (string) data_get($order->shipping_address_snapshot, 'country', '');

        $warehouses = Warehouse::query()
            ->where('fulfillment_enabled', true)
            ->where('active', true)
            ->get()
            ->sortBy(function (Warehouse $w) use ($lines, $country) {
                $total = 0;
                $weight = 0;
                foreach ($lines as $line) {
                    $weight += ((int) ($line['weight_grams'] ?? 0)) * (int) $line['quantity'];
                }
                foreach ($w->rateCards()->where('active', true)->get() as $card) {
                    $quote = $card->quote($country, (float) $weight);
                    if ($quote === null) {
                        continue;
                    }
                    $total += (int) $quote['cost_minor'];
                }

                return $total === 0 ? PHP_INT_MAX : $total;
            })
            ->values();

        return $this->split($lines, $warehouses);
    }

    /** @param array<int, array{variant_id:int, quantity:int, weight_grams?:int}> $lines */
    public function allocateByFastestShipping(Order $order, array $lines): array
    {
        $country = (string) data_get($order->shipping_address_snapshot, 'country', '');

        $warehouses = Warehouse::query()
            ->where('fulfillment_enabled', true)
            ->where('active', true)
            ->get()
            ->sortBy(function (Warehouse $w) use ($country) {
                $minEta = PHP_INT_MAX;
                foreach ($w->rateCards()->where('active', true)->get() as $card) {
                    $quote = $card->quote($country, 1000.0);
                    if ($quote === null) {
                        continue;
                    }
                    $minEta = min($minEta, (int) $quote['eta_days_to']);
                }

                return $minEta;
            })
            ->values();

        return $this->split($lines, $warehouses);
    }

    /** @param array<int, array{variant_id:int, quantity:int, weight_grams?:int}> $lines */
    public function allocateManual(Order $order, array $lines, ?Warehouse $warehouse = null): array
    {
        $warehouse ??= Warehouse::query()->where('is_default', true)->first()
            ?? Warehouse::query()->where('fulfillment_enabled', true)->first();

        if (! $warehouse) {
            throw new AccountingException('No warehouse available for manual allocation.');
        }

        $out = [];
        foreach ($lines as $line) {
            $out[] = [
                'warehouse_id' => $warehouse->id,
                'variant_id' => (int) $line['variant_id'],
                'quantity' => (int) $line['quantity'],
                'weight_grams' => (int) ($line['weight_grams'] ?? 0),
                'bin_id' => null,
            ];
        }

        return $out;
    }

    /**
     * Distribute lines across the given warehouse list, using available
     * stock per warehouse. A line is split if no single warehouse has
     * enough on hand.
     *
     * @param  array<int, array{variant_id:int, quantity:int, weight_grams?:int}>  $lines
     * @param  iterable<Warehouse>  $warehouses
     * @return array<int, array<string,mixed>>
     */
    protected function split(array $lines, iterable $warehouses): array
    {
        $allocations = [];
        foreach ($lines as $line) {
            $remaining = (int) $line['quantity'];
            $variantId = (int) $line['variant_id'];

            $variant = ProductVariant::query()->find($variantId);
            if (! $variant) {
                throw new AccountingException("Variant {$variantId} not found.");
            }

            foreach ($warehouses as $warehouse) {
                if ($remaining <= 0) {
                    break;
                }

                $locId = $warehouse->location_id;
                if (! $locId) {
                    continue;
                }

                $stock = StockItem::query()
                    ->where('variant_id', $variantId)
                    ->where('location_id', $locId)
                    ->first();

                $available = $stock ? $stock->available() : 0;
                if ($available <= 0) {
                    continue;
                }

                $take = min($available, $remaining);

                $binId = null;
                $firstBin = $warehouse->bins()->first();
                if ($firstBin) {
                    $binId = $firstBin->id;
                }

                $allocations[] = [
                    'warehouse_id' => $warehouse->id,
                    'variant_id' => $variantId,
                    'quantity' => $take,
                    'weight_grams' => (int) ($line['weight_grams'] ?? 0),
                    'bin_id' => $binId,
                ];
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new AccountingException(
                    "Insufficient stock to allocate variant {$variant->sku}: short by {$remaining}."
                );
            }
        }

        return $allocations;
    }
}
