<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StandardCost;
use Illuminate\Database\Eloquent\Factories\Factory;

class StandardCostFactory extends Factory
{
    protected $model = StandardCost::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'currency' => 'EUR',
            'unit_cost_minor' => 1500,
            'variance_pct' => 0,
            'effective_from' => now()->toDateString(),
        ];
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function cost(int $unitCostMinor): static
    {
        return $this->state(['unit_cost_minor' => $unitCostMinor]);
    }

    public function withVariance(float $percent): static
    {
        return $this->state(['variance_pct' => $percent]);
    }
}
