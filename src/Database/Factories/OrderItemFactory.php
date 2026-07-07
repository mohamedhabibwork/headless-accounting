<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'variant_id' => ProductVariant::factory(),
            'name' => ucwords($this->faker->words(3, true)),
            'sku' => 'SKU-'.strtoupper($this->faker->bothify('##??##')),
            'quantity' => 1,
            'unit_price_minor' => 1999,
            'unit_tax_minor' => 0,
            'currency' => 'EUR',
            'tax_rate_percent' => 0,
            'tax_inclusive' => false,
            'metadata' => null,
        ];
    }

    public function forOrder(int $orderId): static
    {
        return $this->state(['order_id' => $orderId]);
    }

    public function forVariant(int $variantId, ?string $name = null, ?string $sku = null): static
    {
        return $this->state([
            'variant_id' => $variantId,
            'name' => $name ?? ucwords($this->faker->words(3, true)),
            'sku' => $sku ?? 'SKU-'.strtoupper($this->faker->bothify('##??##')),
        ]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(['quantity' => $quantity]);
    }

    public function unitPrice(int $unitPriceMinor): static
    {
        return $this->state(['unit_price_minor' => $unitPriceMinor]);
    }

    public function withTax(float $percent, int $unitTaxMinor): static
    {
        return $this->state([
            'tax_rate_percent' => $percent,
            'unit_tax_minor' => $unitTaxMinor,
        ]);
    }

    public function taxInclusive(): static
    {
        return $this->state(['tax_inclusive' => true]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
