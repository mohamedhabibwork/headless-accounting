<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Webhook;
use Headless\Accounting\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event_type' => $this->faker->randomElement(['order.created', 'order.paid', 'payment.captured']),
            'http_status' => 200,
            'payload' => ['sample' => $this->faker->word()],
            'attempt' => 1,
            'error' => null,
            'delivered_at' => now(),
        ];
    }

    public function forWebhook(int $webhookId): static
    {
        return $this->state(['webhook_id' => $webhookId]);
    }

    public function succeeded(): static
    {
        return $this->state([
            'http_status' => 200,
            'error' => null,
            'delivered_at' => now(),
        ]);
    }

    public function failed(string $error = 'Connection refused'): static
    {
        return $this->state([
            'http_status' => 500,
            'error' => $error,
        ]);
    }

    public function retry(int $attempt): static
    {
        return $this->state(['attempt' => $attempt]);
    }

    public function event(string $type, array $payload = []): static
    {
        return $this->state([
            'event_type' => $type,
            'payload' => $payload ?: ['sample' => $this->faker->word()],
        ]);
    }

    public function status(int $httpStatus): static
    {
        return $this->state(['http_status' => $httpStatus]);
    }
}
