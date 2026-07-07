<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\ShippingRateCard;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShippingRateCardFactory extends Factory
{
    protected $model = ShippingRateCard::class;

    public function definition(): array
    {
        return [
            'carrier_id' => Carrier::factory(),
            'warehouse_id' => null,
            'service_code' => 'economy',
            'service_name' => 'Economy',
            'destinations' => ['*'],
            'min_weight_grams' => 0,
            'max_weight_grams' => 30000,
            'base_cost_minor' => 500,
            'per_kg_cost_minor' => 200,
            'currency' => 'EUR',
            'free_shipping_threshold_minor' => null,
            'eta_days_from' => 2,
            'eta_days_to' => 4,
            'priority' => 100,
            'active' => true,
        ];
    }

    public function express(): static
    {
        return $this->state([
            'service_code' => 'express',
            'service_name' => 'Express',
            'base_cost_minor' => 1500,
            'per_kg_cost_minor' => 500,
            'eta_days_from' => 1,
            'eta_days_to' => 2,
            'priority' => 50,
        ]);
    }

    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(['warehouse_id' => $warehouseId]);
    }

    public function forCarrier(int $carrierId): static
    {
        return $this->state(['carrier_id' => $carrierId]);
    }

    public function toCountries(array $countries): static
    {
        return $this->state(['destinations' => $countries]);
    }

    public function weightRange(float $minGrams, ?float $maxGrams): static
    {
        return $this->state([
            'min_weight_grams' => $minGrams,
            'max_weight_grams' => $maxGrams,
        ]);
    }

    public function freeShippingOver(int $thresholdMinor): static
    {
        return $this->state(['free_shipping_threshold_minor' => $thresholdMinor]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
