<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ReservationEvent;
use Headless\Accounting\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationEventFactory extends Factory
{
    protected $model = ReservationEvent::class;

    public function definition(): array
    {
        return [
            'stock_reservation_id' => StockReservation::factory(),
            'event' => $this->faker->randomElement(['created', 'released', 'expired', 'fulfilled']),
            'quantity_delta' => $this->faker->numberBetween(-10, 10),
            'note' => null,
            'occurred_at' => now(),
        ];
    }

    public function forReservation(int $reservationId): static
    {
        return $this->state(['stock_reservation_id' => $reservationId]);
    }

    public function created(int $quantityDelta = 1): static
    {
        return $this->state([
            'event' => 'created',
            'quantity_delta' => $quantityDelta,
        ]);
    }

    public function released(int $quantityDelta = -1): static
    {
        return $this->state([
            'event' => 'released',
            'quantity_delta' => $quantityDelta,
        ]);
    }

    public function expired(int $quantityDelta = 0): static
    {
        return $this->state([
            'event' => 'expired',
            'quantity_delta' => $quantityDelta,
        ]);
    }

    public function fulfilled(int $quantityDelta = -1): static
    {
        return $this->state([
            'event' => 'fulfilled',
            'quantity_delta' => $quantityDelta,
        ]);
    }
}
