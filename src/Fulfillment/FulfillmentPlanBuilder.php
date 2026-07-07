<?php

declare(strict_types=1);

namespace Headless\Accounting\Fulfillment;

use Headless\Accounting\Events\FulfillmentPlanCreated;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Warehouse;

/**
 * FulfillmentPlanBuilder — orchestrates the full plan-creation flow:
 *   1. Resolve strategy from config (default: cheapest)
 *   2. Ask the {@see AllocationEngine} to split lines across warehouses
 *   3. Ask the {@see CarrierRateShopper} to rank carrier options for the
 *      primary warehouse
 *   4. Persist a {@see FulfillmentPlan} with both halves ready for picking
 */
class FulfillmentPlanBuilder
{
    public function __construct(
        private readonly AllocationEngine $allocator,
        private readonly CarrierRateShopper $shopper,
    ) {}

    /**
     * @param  array<int, array{variant_id:int, quantity:int, weight_grams?:int}>  $lines
     */
    public function build(
        Order $order,
        array $lines,
        string $strategy = FulfillmentPlan::STRATEGY_PRIORITY,
        ?Warehouse $preferred = null,
    ): FulfillmentPlan {
        $allocations = $this->allocator->allocate($order, $lines, $strategy, $preferred);

        $primary = $this->resolvePrimaryWarehouse($allocations);
        $shipping = [];
        if ($primary !== null) {
            $country = (string) data_get($order->shipping_address_snapshot, 'country', '');
            $weight = 0.0;
            foreach ($allocations as $line) {
                $weight += ((float) ($line['weight_grams'] ?? 0)) * (int) $line['quantity'];
            }
            $ranked = $this->shopper->shop(
                $primary,
                $country,
                $weight,
                (int) $order->grand_total_minor,
                $strategy === FulfillmentPlan::STRATEGY_FASTEST
                    ? CarrierRateShopper::RANK_BY_FASTEST
                    : CarrierRateShopper::RANK_BY_COST,
            );

            foreach (array_slice($ranked, 0, 3) as $idx => $option) {
                $option['selected'] = $idx === 0;
                $shipping[] = $option;
            }
        }

        $number = $this->nextPlanNumber();

        $plan = FulfillmentPlan::create([
            'order_id' => $order->id,
            'number' => $number,
            'strategy' => $strategy,
            'state' => FulfillmentPlan::STATE_ALLOCATED,
            'allocations' => $allocations,
            'shipping_options' => $shipping,
            'planned_at' => now(),
            'allocated_at' => now(),
        ]);

        FulfillmentPlanCreated::dispatch($plan);

        return $plan;
    }

    /** @param  array<int, array<string,mixed>>  $allocations */
    protected function resolvePrimaryWarehouse(array $allocations): ?Warehouse
    {
        if (empty($allocations)) {
            return null;
        }

        $totals = [];
        foreach ($allocations as $line) {
            $wid = (int) ($line['warehouse_id'] ?? 0);
            $totals[$wid] = ($totals[$wid] ?? 0) + (int) ($line['quantity'] ?? 0);
        }
        arsort($totals);
        $primaryId = array_key_first($totals);

        return Warehouse::query()->find($primaryId);
    }

    protected function nextPlanNumber(): string
    {
        $today = now()->format('Ymd');
        $count = FulfillmentPlan::query()->whereDate('created_at', today())->count() + 1;

        return sprintf('FP-%s-%05d', $today, $count);
    }
}
