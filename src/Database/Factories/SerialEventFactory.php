<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\SerialEvent;
use Headless\Accounting\Models\SerialNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class SerialEventFactory extends Factory
{
    protected $model = SerialEvent::class;

    public function definition(): array
    {
        return [
            'serial_number_id' => SerialNumber::factory(),
            'event' => $this->faker->randomElement(['received', 'reserved', 'sold', 'returned', 'repaired', 'shipped']),
            'from_status' => null,
            'to_status' => null,
            'location_id' => null,
            'customer_id' => null,
            'note' => $this->faker->optional(0.4)->sentence(),
            'occurred_at' => now()->subMinutes($this->faker->numberBetween(0, 10000)),
        ];
    }

    public function forSerial(int $serialNumberId): static
    {
        return $this->state(['serial_number_id' => $serialNumberId]);
    }

    public function received(string $toStatus = SerialNumber::STATUS_IN_STOCK): static
    {
        return $this->state([
            'event' => 'received',
            'to_status' => $toStatus,
        ]);
    }

    public function sold(): static
    {
        return $this->state([
            'event' => 'sold',
            'from_status' => SerialNumber::STATUS_RESERVED,
            'to_status' => SerialNumber::STATUS_SOLD,
        ]);
    }

    public function returned(): static
    {
        return $this->state([
            'event' => 'returned',
            'from_status' => SerialNumber::STATUS_SOLD,
            'to_status' => SerialNumber::STATUS_RETURNED,
        ]);
    }

    public function atLocation(int $locationId): static
    {
        return $this->state(['location_id' => $locationId]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function withNote(string $note): static
    {
        return $this->state(['note' => $note]);
    }

    public function occurredAt(string $when): static
    {
        return $this->state(['occurred_at' => $when]);
    }
}
