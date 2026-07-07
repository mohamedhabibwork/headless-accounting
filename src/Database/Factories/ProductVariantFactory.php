<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => null,
            'sku' => $this->faker->unique()->bothify('VAR-####'),
            'barcode' => $this->faker->unique()->ean13(),
            'option_values' => [],
            'weight_grams' => 200,
            'length_mm' => null,
            'width_mm' => null,
            'height_mm' => null,
            'stock_tracked' => true,
            'active' => true,
            'unit_of_measure' => 'pcs',
            'batch_tracked' => false,
            'serial_tracked' => false,
            'expiration_tracked' => false,
            'gs1_gtin' => null,
            'hazard_class' => null,
            'temperature_class' => null,
            'min_stock' => 0,
            'max_stock' => 0,
            'safety_stock' => 0,
            'reorder_point' => 0,
            'reorder_quantity' => 0,
            'lead_time_days' => 0,
        ];
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

    public function withGs1(?string $gtin = null): static
    {
        return $this->state(['gs1_gtin' => $gtin ?? $this->faker->unique()->numerify('############')]);
    }

    public function withReorder(int $min, int $max, int $reorderPoint, int $reorderQty, int $leadTimeDays = 7): static
    {
        return $this->state([
            'min_stock' => $min,
            'max_stock' => $max,
            'safety_stock' => (int) max(0, ($max - $min) / 4),
            'reorder_point' => $reorderPoint,
            'reorder_quantity' => $reorderQty,
            'lead_time_days' => $leadTimeDays,
        ]);
    }

    public function withDimensions(int $length, int $width, int $height, ?int $weightGrams = null): static
    {
        return $this->state([
            'length_mm' => $length,
            'width_mm' => $width,
            'height_mm' => $height,
            'weight_grams' => $weightGrams ?? $this->faker->numberBetween(100, 5000),
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

    public function coldChain(string $temperatureClass = '2-8C'): static
    {
        return $this->state(['temperature_class' => $temperatureClass]);
    }

    public function hazmat(string $hazardClass = 'flammable'): static
    {
        return $this->state(['hazard_class' => $hazardClass]);
    }
}
