<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\TaxZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxZoneFactory extends Factory
{
    protected $model = TaxZone::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->randomElement(['EU', 'FR', 'DE', 'UK', 'US', 'JP']));

        return [
            'code' => $code,
            'name' => ucwords(strtolower($code)).' Zone',
            'description' => $this->faker->optional(0.5)->sentence(),
            'active' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => strtoupper($code)]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function eu(): static
    {
        return $this->state(['code' => 'EU', 'name' => 'European Union']);
    }

    public function france(): static
    {
        return $this->state(['code' => 'FR', 'name' => 'France']);
    }

    public function usa(): static
    {
        return $this->state(['code' => 'US', 'name' => 'United States']);
    }
}
