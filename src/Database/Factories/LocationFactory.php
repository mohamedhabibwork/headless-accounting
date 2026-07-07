<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('LOC-##??')),
            'name' => ucwords($this->faker->words(2, true)),
            'type' => 'warehouse',
            'active' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
