<?php

declare(strict_types=1);

namespace Headless\Accounting\Fulfillment;

use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Warehouse;

/**
 * CarrierRateShopper — collects and ranks shipping quotes for a
 * (warehouse, destination, weight, value) tuple. Quotes are sorted
 * by the requested mode:
 *
 *   - cost     : cheapest total first (ties broken by faster ETA)
 *   - fastest  : shortest eta_days_to first (ties broken by cost)
 *   - eco      : prioritize slower / cheaper services
 */
class CarrierRateShopper
{
    public const RANK_BY_COST = 'cost';

    public const RANK_BY_FASTEST = 'fastest';

    public const RANK_BY_ETA = 'eta';

    /**
     * @return array<int, array<string,mixed>>
     */
    public function shop(
        Warehouse $warehouse,
        string $destinationCountry,
        float $weightGrams,
        int $itemsValueMinor = 0,
        string $mode = self::RANK_BY_COST,
    ): array {
        $cards = $warehouse->rateCards()
            ->with('carrier')
            ->where('active', true)
            ->where(function ($q) use ($warehouse) {
                $q->whereNull('warehouse_id')->orWhere('warehouse_id', $warehouse->id);
            })
            ->get();

        $quotes = [];
        foreach ($cards as $card) {
            $quote = $card->quote($destinationCountry, $weightGrams, $itemsValueMinor);
            if ($quote !== null) {
                $quote['rate_card_id'] = $card->id;
                $quotes[] = $quote;
            }
        }

        return match ($mode) {
            self::RANK_BY_FASTEST, self::RANK_BY_ETA => $this->sortByEta($quotes),
            default => $this->sortByCost($quotes),
        };
    }

    /** @param  array<int, array<string,mixed>>  $quotes */
    protected function sortByCost(array $quotes): array
    {
        usort($quotes, function (array $a, array $b) {
            $cmp = $a['cost_minor'] <=> $b['cost_minor'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['eta_days_to'] <=> $b['eta_days_to'];
        });

        return array_values($quotes);
    }

    /** @param  array<int, array<string,mixed>>  $quotes */
    protected function sortByEta(array $quotes): array
    {
        usort($quotes, function (array $a, array $b) {
            $cmp = $a['eta_days_to'] <=> $b['eta_days_to'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['cost_minor'] <=> $b['cost_minor'];
        });

        return array_values($quotes);
    }

    public function cheapest(Warehouse $warehouse, string $destinationCountry, float $weightGrams, int $itemsValueMinor = 0): ?array
    {
        $quotes = $this->shop($warehouse, $destinationCountry, $weightGrams, $itemsValueMinor, self::RANK_BY_COST);

        return $quotes[0] ?? null;
    }

    public function fastest(Warehouse $warehouse, string $destinationCountry, float $weightGrams, int $itemsValueMinor = 0): ?array
    {
        $quotes = $this->shop($warehouse, $destinationCountry, $weightGrams, $itemsValueMinor, self::RANK_BY_FASTEST);

        return $quotes[0] ?? null;
    }

    public function quoteForOrder(Warehouse $warehouse, Order $order, string $mode = self::RANK_BY_COST): ?array
    {
        $country = (string) data_get($order->shipping_address_snapshot, 'country', '');
        $weight = (float) data_get($order->metadata, 'total_weight_grams', 0);
        $value = (int) $order->grand_total_minor;

        return $this->shop($warehouse, $country, $weight, $value, $mode)[0] ?? null;
    }

    /** @return array<int, Carrier> */
    public function activeCarriers(): array
    {
        return Carrier::query()->where('active', true)->get()->all();
    }
}
