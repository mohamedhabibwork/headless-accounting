<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\PackStation;
use Headless\Accounting\Models\PickList;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackStationFactory extends Factory
{
    protected $model = PackStation::class;

    public function definition(): array
    {
        return [
            'pick_list_id' => PickList::factory(),
            'number' => 'PK-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'packer_name' => $this->faker->optional(0.5)->name(),
            'carton_type' => 'box-m',
            'weight_grams' => 1500,
            'length_mm' => 300,
            'width_mm' => 200,
            'height_mm' => 100,
            'items' => [],
            'state' => PackStation::STATE_OPEN,
            'packed_at' => null,
            'sealed_at' => null,
        ];
    }

    public function forPickList(int $pickListId): static
    {
        return $this->state(['pick_list_id' => $pickListId]);
    }

    public function open(): static
    {
        return $this->state(['state' => PackStation::STATE_OPEN]);
    }

    public function packed(?string $packer = null): static
    {
        return $this->state([
            'state' => PackStation::STATE_PACKED,
            'packer_name' => $packer ?? $this->faker->name(),
            'packed_at' => now(),
        ]);
    }

    public function sealed(): static
    {
        return $this->state([
            'state' => PackStation::STATE_SEALED,
            'sealed_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(['state' => PackStation::STATE_SHIPPED]);
    }

    public function dimensions(int $length, int $width, int $height, int $weight = 1500): static
    {
        return $this->state([
            'length_mm' => $length,
            'width_mm' => $width,
            'height_mm' => $height,
            'weight_grams' => $weight,
        ]);
    }

    public function carton(string $type): static
    {
        return $this->state(['carton_type' => $type]);
    }

    public function withItems(array $items): static
    {
        return $this->state(['items' => $items]);
    }
}
