<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ProductOption;
use Headless\Accounting\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductOptionValueFactory extends Factory
{
    protected $model = ProductOptionValue::class;

    public function definition(): array
    {
        return [
            'option_id' => ProductOption::factory(),
            'value' => ucwords($this->faker->unique()->word()),
            'position' => $this->faker->numberBetween(0, 10),
        ];
    }

    public function forOption(int $optionId): static
    {
        return $this->state(['option_id' => $optionId]);
    }

    public function value(string $value): static
    {
        return $this->state(['value' => $value]);
    }

    public function position(int $position): static
    {
        return $this->state(['position' => $position]);
    }
}
