<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'decimals' => 2,
            'active' => true,
        ];
    }

    public function code(string $code, string $name, string $symbol, int $decimals = 2): static
    {
        return $this->state([
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol,
            'decimals' => $decimals,
        ]);
    }

    public function eur(): static
    {
        return $this->state(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2]);
    }

    public function usd(): static
    {
        return $this->state(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2]);
    }

    public function gbp(): static
    {
        return $this->state(['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'decimals' => 2]);
    }

    public function jpy(): static
    {
        return $this->state(['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimals' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
