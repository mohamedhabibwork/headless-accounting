<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Cart;
use Headless\Accounting\Models\CartItem;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 1,
            'unit_price_minor' => 1999,
            'currency' => 'EUR',
            'adjustments' => null,
        ];
    }

    public function forCart(int $cartId): static
    {
        return $this->state(['cart_id' => $cartId]);
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(['quantity' => $quantity]);
    }

    public function unitPrice(int $priceMinor): static
    {
        return $this->state(['unit_price_minor' => $priceMinor]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function withAdjustments(array $adjustments): static
    {
        return $this->state(['adjustments' => $adjustments]);
    }
}
