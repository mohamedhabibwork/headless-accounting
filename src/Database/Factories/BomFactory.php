<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Bom;
use Headless\Accounting\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomFactory extends Factory
{
    protected $model = Bom::class;

    public function definition(): array
    {
        $name = 'BOM '.strtoupper($this->faker->unique()->bothify('##??##'));

        return [
            'company_id' => null,
            'product_id' => Product::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('BOM-##??##')),
            'name' => $name,
            'quantity_per_unit' => 1,
            'active' => true,
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function perUnit(int $quantity): static
    {
        return $this->state(['quantity_per_unit' => $quantity]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
