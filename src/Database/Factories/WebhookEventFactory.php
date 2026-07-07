<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'driver' => 'stripe',
            'provider_event_id' => $this->faker->unique()->bothify('evt_????????'),
            'event_type' => $this->faker->randomElement([
                'payment_intent.succeeded', 'charge.succeeded', 'invoice.paid',
            ]),
            'payload' => [
                'object' => ['id' => $this->faker->uuid(), 'amount' => 1999],
            ],
            'received_at' => now(),
            'processed_at' => null,
            'outcome' => null,
        ];
    }

    public function driver(string $driver): static
    {
        return $this->state(['driver' => $driver]);
    }

    public function stripe(): static
    {
        return $this->state(['driver' => 'stripe']);
    }

    public function paypal(): static
    {
        return $this->state(['driver' => 'paypal']);
    }

    public function eventType(string $type, array $payload = []): static
    {
        return $this->state([
            'event_type' => $type,
            'payload' => $payload ?: ['object' => ['id' => $this->faker->uuid()]],
        ]);
    }

    public function processed(string $outcome = 'ok'): static
    {
        return $this->state([
            'processed_at' => now(),
            'outcome' => $outcome,
        ]);
    }

    public function failed(string $reason = 'handler_error'): static
    {
        return $this->state([
            'processed_at' => now(),
            'outcome' => 'failed',
        ]);
    }

    public function ignored(): static
    {
        return $this->state(['outcome' => 'ignored']);
    }
}
