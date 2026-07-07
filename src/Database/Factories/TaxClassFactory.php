<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxClassFactory extends Factory
{
    protected $model = TaxClass::class;

    public function definition(): array
    {
        return [
            'name' => ucwords($this->faker->unique()->words(2, true)),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function standard(): static
    {
        return $this->state(['name' => 'Standard', 'slug' => 'standard']);
    }

    public function reduced(): static
    {
        return $this->state(['name' => 'Reduced', 'slug' => 'reduced']);
    }

    public function zero(): static
    {
        return $this->state(['name' => 'Zero', 'slug' => 'zero']);
    }

    public function exempt(): static
    {
        return $this->state(['name' => 'Exempt', 'slug' => 'exempt']);
    }

    public function digital(): static
    {
        return $this->state(['name' => 'Digital Services', 'slug' => 'digital']);
    }

    public function slug(string $slug): static
    {
        return $this->state(['slug' => $slug]);
    }

    public function name(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
