<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductOption;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'code' => strtolower($this->faker->unique()->randomElement(['color', 'size', 'material', 'fit', 'style'])),
            'name' => ucfirst($this->faker->randomElement(['Color', 'Size', 'Material', 'Fit', 'Style'])),
            'position' => $this->faker->numberBetween(0, 5),
        ];
    }

    public function color(): static
    {
        return $this->state(['code' => 'color', 'name' => 'Color']);
    }

    public function size(): static
    {
        return $this->state(['code' => 'size', 'name' => 'Size']);
    }

    public function material(): static
    {
        return $this->state(['code' => 'material', 'name' => 'Material']);
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function position(int $position): static
    {
        return $this->state(['position' => $position]);
    }

    public function code(string $code, ?string $name = null): static
    {
        return $this->state(['code' => $code, 'name' => $name ?? ucfirst($code)]);
    }
}
