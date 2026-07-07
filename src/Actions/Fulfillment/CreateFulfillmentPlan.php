<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Fulfillment;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\FulfillmentPlanCreated;
use Headless\Accounting\Fulfillment\AllocationEngine;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Support\Config;

/**
 * CreateFulfillmentPlan — runs the allocation engine against an order
 * and persists a {@see FulfillmentPlan} (allocations + shipping options).
 * Subsequent actions (pick / pack / ship) read from this plan.
 */
final class CreateFulfillmentPlan extends Action
{
    public function __construct(private readonly AllocationEngine $allocator) {}

    /**
     * @param  array<int, array{variant_id:int, quantity:int, weight_grams?:int}>  $lines
     */
    protected function handle(
        Order $order,
        array $lines,
        string $strategy = FulfillmentPlan::STRATEGY_PRIORITY,
        ?Warehouse $preferredWarehouse = null,
    ): FulfillmentPlan {
        $allocations = $this->allocator->allocate($order, $lines, $strategy, $preferredWarehouse);

        return tap(FulfillmentPlan::create([
            'order_id' => $order->id,
            'number' => $this->nextNumber(),
            'strategy' => $strategy,
            'state' => FulfillmentPlan::STATE_ALLOCATED,
            'allocations' => $allocations,
            'planned_at' => now(),
            'allocated_at' => now(),
        ]), fn (FulfillmentPlan $plan) => FulfillmentPlanCreated::dispatch($plan));
    }

    protected function nextNumber(): string
    {
        $today = now()->format('Ymd');
        $count = FulfillmentPlan::query()->whereDate('created_at', today())->count() + 1;
        $prefix = Config::string('headless-accounting.number_prefixes.fulfillment_plan', 'FP');

        return sprintf('%s-%s-%05d', $prefix, $today, $count);
    }
}
