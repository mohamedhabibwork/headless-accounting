<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'token' => $this->faker->unique()->uuid(),
            'customer_id' => null,
            'channel_code' => 'web',
            'currency' => 'EUR',
            'locale' => 'en',
            'metadata' => null,
            'expires_at' => now()->addDay(),
        ];
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function guest(): static
    {
        return $this->state(['customer_id' => null]);
    }

    public function onChannel(string $channel): static
    {
        return $this->state(['channel_code' => $channel]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function locale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }

    public function metadata(array $metadata): static
    {
        return $this->state(['metadata' => $metadata]);
    }

    public function expiresIn(int $hours): static
    {
        return $this->state(['expires_at' => now()->addHours($hours)]);
    }
}
