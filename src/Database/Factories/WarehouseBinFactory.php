<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\WarehouseBin;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseBinFactory extends Factory
{
    protected $model = WarehouseBin::class;

    public function definition(): array
    {
        return [
            'zone_id' => null,
            'code' => strtoupper($this->faker->bothify('A-##-##')),
            'aisle' => strtoupper($this->faker->bothify('A##')),
            'rack' => strtoupper($this->faker->bothify('R##')),
            'shelf' => strtoupper($this->faker->bothify('S##')),
            'level' => strtoupper($this->faker->bothify('L##')),
            'position' => strtoupper($this->faker->bothify('P##')),
            'barcode' => $this->faker->unique()->ean13(),
            'qr_code' => null,
            'capacity_units' => 100,
            'current_units' => 0,
            'max_weight_grams' => 50000,
            'current_weight_grams' => 0,
            'active' => true,
        ];
    }

    public function forZone(int $zoneId): static
    {
        return $this->state(['zone_id' => $zoneId]);
    }

    public function coordinate(string $aisle, string $rack, string $shelf, string $level, string $position): static
    {
        return $this->state([
            'aisle' => $aisle,
            'rack' => $rack,
            'shelf' => $shelf,
            'level' => $level,
            'position' => $position,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function withCapacity(int $units, int $weightGrams): static
    {
        return $this->state([
            'capacity_units' => $units,
            'max_weight_grams' => $weightGrams,
        ]);
    }

    public function partiallyFilled(int $units, int $weightGrams): static
    {
        return $this->state([
            'current_units' => $units,
            'current_weight_grams' => $weightGrams,
        ]);
    }

    public function withQr(?string $qrCode = null): static
    {
        return $this->state(['qr_code' => $qrCode ?? 'BIN-'.$this->faker->unique()->bothify('????????')]);
    }
}
