<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Order;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Support\Config;

final class CreateOrder extends Action
{
    protected function handle(
        ?Customer $customer = null,
        ?string $channel = null,
        ?string $currency = null,
        ?string $locale = null,
        array $billingAddress = [],
        array $shippingAddress = [],
        array $metadata = [],
    ): Order {
        $channel ??= Config::string('headless-accounting.channels.default');
        $currency ??= Config::string('headless-accounting.currency.default');
        $locale ??= Config::string('headless-accounting.locale.default');
        $order = new Order([
            'number' => $this->nextNumber(),
            'customer_id' => $customer?->getKey(),
            'channel_code' => $channel,
            'currency' => $currency,
            'fx_currency' => $currency,
            'fx_rate' => 1.0,
            'state' => Order::STATE_CART,
            'locale' => $locale,
            'email' => $customer?->email,
            'billing_address_snapshot' => $billingAddress,
            'shipping_address_snapshot' => $shippingAddress,
            'metadata' => $metadata,
        ]);
        $order->save();
        $order->recordEvent('order.created', [
            'customer_id' => $customer?->getKey(),
            'channel' => $channel,
            'currency' => $currency,
        ]);

        return $order;
    }

    private function nextNumber(): string
    {
        $year = date('Y');
        // Padded order count, unique per year. Cheap; replace with a sequence table in production.
        $count = Order::withTrashed()->whereYear('created_at', $year)->count() + 1;
        $prefix = Config::string('headless-accounting.number_prefixes.order', 'ORD');

        return sprintf('%s-%s-%06d', $prefix, $year, $count);
    }
}
