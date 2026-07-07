<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'location_id' => null,
            'parent_id' => null,
            'branch_id' => null,
            'owner_company_id' => null,
            'inter_company' => false,
            'consignment' => false,
            'virtual' => false,
            'in_transit' => false,
            'quarantine_only' => false,
            'returns_only' => false,
            'code' => 'WH-'.strtoupper($this->faker->bothify('##??')),
            'name' => $this->faker->company().' Warehouse',
            'type' => 'warehouse',
            'fulfillment_enabled' => true,
            'stocktake_enabled' => true,
            'is_default' => false,
            'priority' => 100,
            'shipping_address' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'postal_code' => $this->faker->postcode(),
                'country' => 'FR',
            ],
            'contact' => null,
            'capabilities' => ['hazmat' => false, 'cold_chain' => false],
            'opening_hours' => ['mon' => '08:00-18:00', 'tue' => '08:00-18:00'],
            'latitude' => null,
            'longitude' => null,
            'timezone' => 'Europe/Paris',
            'active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true, 'priority' => 1]);
    }

    public function fulfillmentDisabled(): static
    {
        return $this->state(['fulfillment_enabled' => false]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function asStore(): static
    {
        return $this->state(['type' => Warehouse::TYPE_STORE]);
    }

    public function asDropship(): static
    {
        return $this->state(['type' => Warehouse::TYPE_DROPSHIP]);
    }

    public function asInTransit(): static
    {
        return $this->state([
            'type' => Warehouse::TYPE_TRANSIT,
            'in_transit' => true,
            'fulfillment_enabled' => false,
        ]);
    }

    public function asQuarantine(): static
    {
        return $this->state([
            'type' => Warehouse::TYPE_QUARANTINE,
            'quarantine_only' => true,
            'fulfillment_enabled' => false,
        ]);
    }

    public function asReturns(): static
    {
        return $this->state([
            'type' => Warehouse::TYPE_RETURNS,
            'returns_only' => true,
        ]);
    }

    public function asConsignment(): static
    {
        return $this->state([
            'type' => Warehouse::TYPE_CONSIGNMENT,
            'consignment' => true,
        ]);
    }

    public function asVirtual(): static
    {
        return $this->state([
            'type' => Warehouse::TYPE_VIRTUAL,
            'virtual' => true,
        ]);
    }

    public function priority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function at(string $city, string $country, float $lat, float $lng): static
    {
        return $this->state([
            'shipping_address' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $city,
                'postal_code' => $this->faker->postcode(),
                'country' => $country,
            ],
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    public function withCapabilities(array $capabilities): static
    {
        return $this->state(['capabilities' => $capabilities]);
    }

    public function coldChain(bool $enabled = true): static
    {
        return $this->state(['capabilities' => array_merge(
            ['hazmat' => false, 'cold_chain' => $enabled],
        )]);
    }

    public function timezone(string $timezone): static
    {
        return $this->state(['timezone' => $timezone]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }
}
