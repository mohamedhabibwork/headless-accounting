<?php

declare(strict_types=1);

namespace Headless\Accounting\Reporting;

use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Support\Config;

/**
 * InventoryValuationReport + COGS / stock-movement helpers.
 */
class InventoryValuationReport
{
    public function valuationByVariant(int $companyId, ?string $currency = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');

        return CostLayer::query()
            ->selectRaw('variant_id, location_id, SUM(quantity_remaining * unit_cost_minor) as value_minor, SUM(quantity_remaining) as qty')
            ->where('company_id', $companyId)
            ->where('currency', $currency)
            ->groupBy('variant_id', 'location_id')
            ->get()
            ->map(fn ($r) => [
                'variant_id' => (int) $r->variant_id,
                'location_id' => (int) $r->location_id,
                'qty' => (int) $r->qty,
                'value_minor' => (int) $r->value_minor,
            ])
            ->all();
    }

    public function stockMovements(int $companyId, ?string $currency = null, ?Carbon\CarbonImmutable $from = null, ?Carbon\CarbonImmutable $to = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');
        $q = StockMovement::query()
            ->with('stockItem.variant')
            ->whereBetween('occurred_at', [
                ($from ?? now()->subMonths(1))->toDateTimeString(),
                ($to ?? now())->toDateTimeString(),
            ]);

        return $q->get()->map(fn ($m) => [
            'occurred_at' => $m->occurred_at?->toIso8601String(),
            'reason' => $m->reason,
            'variant_id' => $m->stockItem?->variant_id,
            'quantity' => (int) $m->quantity,
            'balance' => (int) $m->balance_after,
        ])->all();
    }
}
