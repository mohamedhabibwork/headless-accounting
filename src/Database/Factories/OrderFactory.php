<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'ORD-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'customer_id' => null,
            'channel_code' => 'web',
            'currency' => 'EUR',
            'fx_currency' => null,
            'fx_rate' => null,
            'state' => Order::STATE_CART,
            'subtotal_minor' => 0,
            'tax_total_minor' => 0,
            'shipping_minor' => 0,
            'discount_total_minor' => 0,
            'grand_total_minor' => 0,
            'item_count' => 0,
            'locale' => 'en',
            'email' => $this->faker->safeEmail(),
            'billing_address_snapshot' => null,
            'shipping_address_snapshot' => null,
            'metadata' => null,
            'placed_at' => null,
            'paid_at' => null,
            'fulfilled_at' => null,
            'closed_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function placed(): static
    {
        return $this->state([
            'state' => Order::STATE_PLACED,
            'placed_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'state' => Order::STATE_PAID,
            'placed_at' => now()->subMinutes(5),
            'paid_at' => now(),
        ]);
    }

    public function fulfilled(): static
    {
        return $this->state([
            'state' => Order::STATE_FULFILLED,
            'placed_at' => now()->subHour(),
            'paid_at' => now()->subMinutes(45),
            'fulfilled_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'state' => Order::STATE_CANCELLED,
            'placed_at' => now()->subMinutes(10),
            'cancelled_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state([
            'state' => Order::STATE_REFUNDED,
            'placed_at' => now()->subDay(),
            'paid_at' => now()->subDay()->addMinutes(10),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'state' => Order::STATE_CLOSED,
            'placed_at' => now()->subWeek(),
            'paid_at' => now()->subWeek()->addMinutes(10),
            'fulfilled_at' => now()->subWeek()->addHour(),
            'closed_at' => now(),
        ]);
    }

    public function inCurrency(string $currency, ?string $fxCurrency = null, ?float $fxRate = null): static
    {
        return $this->state([
            'currency' => $currency,
            'fx_currency' => $fxCurrency,
            'fx_rate' => $fxRate,
        ]);
    }

    public function onChannel(string $channel): static
    {
        return $this->state(['channel_code' => $channel]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function withTotals(int $subtotal, int $grandTotal, int $tax = 0, int $shipping = 0, int $discount = 0): static
    {
        return $this->state([
            'subtotal_minor' => $subtotal,
            'tax_total_minor' => $tax,
            'shipping_minor' => $shipping,
            'discount_total_minor' => $discount,
            'grand_total_minor' => $grandTotal,
        ]);
    }

    public function withShippingAddress(string $country = 'FR'): static
    {
        return $this->state([
            'shipping_address_snapshot' => [
                'country' => $country,
                'city' => $this->faker->city(),
                'postal_code' => $this->faker->postcode(),
            ],
        ]);
    }
}
