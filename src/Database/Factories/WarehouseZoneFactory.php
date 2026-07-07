<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\WarehouseZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseZoneFactory extends Factory
{
    protected $model = WarehouseZone::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => null,
            'code' => strtoupper($this->faker->bothify('ZONE-##')),
            'name' => ucwords($this->faker->words(2, true)),
            'kind' => 'storage',
            'is_default_pick' => false,
            'is_default_pack' => false,
            'position' => 0,
        ];
    }

    public function pickFace(): static
    {
        return $this->state(['kind' => 'pick_face', 'is_default_pick' => true]);
    }

    public function packing(): static
    {
        return $this->state(['kind' => 'packing', 'is_default_pack' => true]);
    }

    public function receiving(): static
    {
        return $this->state(['kind' => 'receiving']);
    }

    public function shipping(): static
    {
        return $this->state(['kind' => 'shipping']);
    }

    public function quarantine(): static
    {
        return $this->state(['kind' => 'quarantine']);
    }

    public function returns(): static
    {
        return $this->state(['kind' => 'returns']);
    }

    public function crossDock(): static
    {
        return $this->state(['kind' => 'cross_dock']);
    }

    public function production(): static
    {
        return $this->state(['kind' => 'production']);
    }

    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(['warehouse_id' => $warehouseId]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function position(int $position): static
    {
        return $this->state(['position' => $position]);
    }

    public function defaultPickAndPack(): static
    {
        return $this->state([
            'kind' => 'pick_face',
            'is_default_pick' => true,
            'is_default_pack' => true,
        ]);
    }
}
