<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => $this->faker->words(2, true).' Webhook',
            'url' => $this->faker->url(),
            'secret' => bin2hex(random_bytes(16)),
            'event_types' => ['order.created', 'order.paid'],
            'content_type' => 'application/json',
            'active' => true,
        ];
    }

    public function forCompany(int $companyId): static
    {
        return $this->state(['company_id' => $companyId]);
    }

    public function active(): static
    {
        return $this->state(['active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function url(string $url): static
    {
        return $this->state(['url' => $url]);
    }

    public function subscribingTo(array $events): static
    {
        return $this->state(['event_types' => $events]);
    }

    public function name(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
