<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Discount;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;
use Headless\Accounting\Discounts\DiscountEngine;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountUsage;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderAdjustment;

/**
 * ApplyDiscount — applies a single discount (or a code-named one) to an
 * Order, recording an OrderAdjustment per line and a DiscountUsage row
 * for downstream limits.
 */
final class ApplyDiscount extends Action
{
    public function __construct(private readonly DiscountEngine $engine) {}

    protected function handle(
        Order $order,
        Discount|int|string $discount,
        ?string $couponCode = null,
    ): Order {
        $resolved = match (true) {
            $discount instanceof Discount => $discount,
            is_int($discount) => Discount::findOrFail($discount),
            is_string($discount) => Discount::query()->where('code', $discount)->firstOrFail(),
            default => throw new \InvalidArgumentException('Invalid discount argument.'),
        };

        $items = iterator_to_array($order->items()->cursor());
        $ctx = new EvaluationContext(
            order: $order,
            items: $items,
            customer: $order->customer,
            channel: $order->channel_code,
            extras: ['coupon' => $couponCode ?? $resolved->code],
        );

        $applications = $this->engine->run([$resolved], $ctx);

        foreach ($applications as $app) {
            foreach ($app->lines() as $line) {
                OrderAdjustment::create([
                    'order_id' => $order->id,
                    'order_item_id' => $order->items()->where('variant_id', $line['variant_id'] ?? null)->value('id'),
                    'discount_id' => $resolved->id,
                    'type' => 'discount',
                    'name' => $resolved->name,
                    'amount_minor' => -1 * (int) $line['amount']->amount,
                    'currency' => $order->currency,
                ]);
            }

            DiscountUsage::create([
                'discount_id' => $resolved->id,
                'customer_id' => $order->customer_id,
                'source_type' => $order->getMorphClass(),
                'source_id' => $order->id,
                'amount_minor' => $app->total->amount,
                'currency' => $order->currency,
            ]);
        }

        // Re-run totals so grand total reflects.
        app(CalculateOrderTotals::class)->execute($order);

        return $order->fresh(['adjustments']);
    }
}
