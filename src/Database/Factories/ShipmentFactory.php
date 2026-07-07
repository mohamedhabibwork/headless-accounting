<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'number' => 'SHIP-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'order_id' => Order::factory(),
            'fulfillment_plan_id' => null,
            'pick_list_id' => null,
            'pack_station_id' => null,
            'warehouse_id' => null,
            'carrier_id' => null,
            'shipping_rate_card_id' => null,
            'carrier_code' => 'dhl',
            'service_code' => 'express',
            'state' => Shipment::STATE_PENDING,
            'carrier' => 'DHL Express',
            'tracking_number' => strtoupper($this->faker->bothify('??########??')),
            'tracking_url' => null,
            'weight_grams' => 1500,
            'length_mm' => 300,
            'width_mm' => 200,
            'height_mm' => 100,
            'cost_minor' => 1200,
            'currency' => 'EUR',
            'items' => [],
            'customs' => null,
            'label_url' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'metadata' => null,
        ];
    }

    public function forOrder(int $orderId): static
    {
        return $this->state(['order_id' => $orderId]);
    }

    public function picked(): static
    {
        return $this->state(['state' => Shipment::STATE_PICKED]);
    }

    public function packed(): static
    {
        return $this->state(['state' => Shipment::STATE_PACKED]);
    }

    public function shipped(): static
    {
        return $this->state([
            'state' => Shipment::STATE_SHIPPED,
            'shipped_at' => now(),
        ]);
    }

    public function inTransit(): static
    {
        return $this->state(['state' => Shipment::STATE_IN_TRANSIT]);
    }

    public function delivered(): static
    {
        return $this->state([
            'state' => Shipment::STATE_DELIVERED,
            'shipped_at' => now()->subDay(),
            'delivered_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => Shipment::STATE_CANCELLED]);
    }

    public function returned(): static
    {
        return $this->state(['state' => Shipment::STATE_RETURNED]);
    }

    public function forCarrier(int $carrierId): static
    {
        return $this->state(['carrier_id' => $carrierId]);
    }

    public function carrier(string $code, string $name, string $service = 'express'): static
    {
        return $this->state([
            'carrier_code' => $code,
            'carrier' => $name,
            'service_code' => $service,
        ]);
    }

    public function withTracking(string $trackingNumber, ?string $url = null): static
    {
        return $this->state([
            'tracking_number' => $trackingNumber,
            'tracking_url' => $url ?? "https://track.example.com/{$trackingNumber}",
        ]);
    }

    public function cost(int $costMinor, string $currency = 'EUR'): static
    {
        return $this->state([
            'cost_minor' => $costMinor,
            'currency' => $currency,
        ]);
    }
}
