<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ProductBarcode;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'barcode' => $this->faker->unique()->ean13(),
            'symbology' => 'EAN13',
            'is_primary' => false,
            'label_template' => null,
            'active' => true,
        ];
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }

    public function symbology(string $symbology): static
    {
        return $this->state(['symbology' => $symbology]);
    }

    public function qrCode(): static
    {
        return $this->state([
            'symbology' => 'QR',
            'barcode' => $this->faker->unique()->uuid(),
        ]);
    }

    public function gs1_128(): static
    {
        return $this->state([
            'symbology' => 'GS1_128',
            'barcode' => $this->faker->unique()->numerify('(01)##########(17)######'),
        ]);
    }

    public function upcA(): static
    {
        return $this->state([
            'symbology' => 'UPC_A',
            'barcode' => $this->faker->unique()->numerify('##########'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function withLabel(string $template): static
    {
        return $this->state(['label_template' => $template]);
    }
}
