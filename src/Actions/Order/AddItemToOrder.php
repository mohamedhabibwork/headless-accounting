<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Order;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Pricing\PricingResolver;

final class AddItemToOrder extends Action
{
    public function __construct(private readonly PricingResolver $pricing) {}

    protected function handle(
        Order $order,
        ProductVariant $variant,
        int $quantity = 1,
        ?int $unitPriceMinorOverride = null,
    ): OrderItem {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be > 0.');
        }

        $resolved = $unitPriceMinorOverride === null
            ? $this->pricing->resolve($variant, $order->currency, null, $order->customer)->amount->amount
            : $unitPriceMinorOverride;

        // Merge if the variant already exists on the order.
        $existing = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('variant_id', $variant->id)
            ->first();

        if ($existing) {
            $existing->quantity = (int) $existing->quantity + $quantity;
            $existing->save();
            $order->recordEvent('order.item.merged', ['variant_id' => $variant->id, 'quantity' => $quantity]);

            return $existing;
        }

        $item = OrderItem::create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'name' => $variant->product?->name ?? $variant->name ?? '',
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_price_minor' => $resolved,
            'currency' => $order->currency,
            'tax_inclusive' => (bool) ($order->metadata['tax_inclusive'] ?? false),
        ]);

        $order->recordEvent('order.item.added', [
            'variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price_minor' => $resolved,
        ]);

        return $item;
    }
}
