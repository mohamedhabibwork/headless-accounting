<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        $base = 'EUR';
        $quote = $this->faker->randomElement(['USD', 'GBP', 'JPY', 'CHF']);

        return [
            'base_currency' => $base,
            'quote_currency' => $quote,
            'rate' => $this->faker->randomFloat(8, 0.5, 1.5),
            'effective_at' => now()->toDateString(),
            'source' => $this->faker->randomElement(['ecb', 'manual', 'openexchangerates', 'fixer']),
        ];
    }

    public function pair(string $base, string $quote, float $rate): static
    {
        return $this->state([
            'base_currency' => $base,
            'quote_currency' => $quote,
            'rate' => $rate,
        ]);
    }

    public function source(string $source): static
    {
        return $this->state(['source' => $source]);
    }

    public function effectiveOn(string $date): static
    {
        return $this->state(['effective_at' => $date]);
    }

    public function ecb(): static
    {
        return $this->state(['source' => 'ecb']);
    }
}
