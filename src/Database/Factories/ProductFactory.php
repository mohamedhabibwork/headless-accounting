<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => ucwords($this->faker->words(3, true)),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->paragraph(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'tax_class_id' => null,
            'stock_tracked' => true,
            'active' => true,
            'attributes' => null,
            'item_type' => Product::TYPE_FINISHED_GOOD,
            'batch_tracked' => false,
            'serial_tracked' => false,
            'expiration_tracked' => false,
            'unit_of_measure' => 'pcs',
            'hazard_class' => null,
            'temperature_class' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $p) {
            ProductVariant::factory()->create(['product_id' => $p->id, 'sku' => $p->sku.'-A']);
        });
    }

    public function ofType(string $itemType): static
    {
        return $this->state(['item_type' => $itemType]);
    }

    public function rawMaterial(): static
    {
        return $this->state([
            'item_type' => Product::TYPE_RAW_MATERIAL,
            'stock_tracked' => true,
        ]);
    }

    public function service(): static
    {
        return $this->state([
            'item_type' => Product::TYPE_SERVICE,
            'stock_tracked' => false,
        ]);
    }

    public function kit(): static
    {
        return $this->state(['item_type' => Product::TYPE_KIT]);
    }

    public function batchTracked(): static
    {
        return $this->state(['batch_tracked' => true]);
    }

    public function serialTracked(): static
    {
        return $this->state(['serial_tracked' => true]);
    }

    public function expirationTracked(): static
    {
        return $this->state([
            'expiration_tracked' => true,
            'batch_tracked' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function notStockTracked(): static
    {
        return $this->state(['stock_tracked' => false]);
    }

    public function ofUnit(string $unitOfMeasure): static
    {
        return $this->state(['unit_of_measure' => $unitOfMeasure]);
    }

    public function hazmat(string $hazardClass = 'flammable'): static
    {
        return $this->state(['hazard_class' => $hazardClass]);
    }

    public function coldChain(string $temperatureClass = '2-8C'): static
    {
        return $this->state(['temperature_class' => $temperatureClass]);
    }
}
