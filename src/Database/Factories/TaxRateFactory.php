<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\TaxClass;
use Headless\Accounting\Models\TaxRate;
use Headless\Accounting\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        $percents = [2.0, 5.0, 7.0, 10.0, 13.0, 16.0, 19.0, 20.0, 21.0, 25.0];

        return [
            'zone_id' => TaxZone::factory(),
            'tax_class_id' => TaxClass::factory(),
            'name' => $percents[array_rand($percents)].'% VAT',
            'percent' => $percents[array_rand($percents)],
            'compound' => false,
            'priority' => 1,
            'active' => true,
        ];
    }

    public function forZone(int $zoneId): static
    {
        return $this->state(['zone_id' => $zoneId]);
    }

    public function forClass(int $classId): static
    {
        return $this->state(['tax_class_id' => $classId]);
    }

    public function percent(float $percent): static
    {
        return $this->state([
            'percent' => $percent,
            'name' => $percent.'% VAT',
        ]);
    }

    public function compound(): static
    {
        return $this->state(['compound' => true]);
    }

    public function priority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function standard(): static
    {
        return $this->state(['percent' => 20.0, 'name' => '20% Standard']);
    }

    public function reduced(): static
    {
        return $this->state(['percent' => 10.0, 'name' => '10% Reduced']);
    }

    public function zero(): static
    {
        return $this->state(['percent' => 0.0, 'name' => 'Zero Rated']);
    }
}
