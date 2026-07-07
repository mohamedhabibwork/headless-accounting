<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\EventStream;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventStreamFactory extends Factory
{
    protected $model = EventStream::class;

    public function definition(): array
    {
        return [
            'subject_type' => null,
            'subject_id' => null,
            'type' => $this->faker->randomElement([
                'order.created', 'order.placed', 'order.paid',
                'invoice.issued', 'payment.captured', 'shipment.shipped',
            ]),
            'payload' => ['sample' => $this->faker->word()],
            'occurred_at' => now(),
        ];
    }

    public function forSubject(string $type, int $id): static
    {
        return $this->state([
            'subject_type' => $type,
            'subject_id' => $id,
        ]);
    }

    public function type(string $type, array $payload = []): static
    {
        return $this->state([
            'type' => $type,
            'payload' => $payload ?: ['sample' => $this->faker->word()],
        ]);
    }

    public function occurredOn(string $date): static
    {
        return $this->state(['occurred_at' => $date]);
    }
}
