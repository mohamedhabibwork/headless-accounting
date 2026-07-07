<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = ucwords($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->optional(0.7)->sentence(),
            'position' => $this->faker->numberBetween(0, 100),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function childOf(int $parentId): static
    {
        return $this->state(['parent_id' => $parentId]);
    }

    public function position(int $position): static
    {
        return $this->state(['position' => $position]);
    }

    public function root(): static
    {
        return $this->state(['position' => 1]);
    }
}
