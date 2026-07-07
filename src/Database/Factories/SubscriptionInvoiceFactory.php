<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Subscription;
use Headless\Accounting\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionInvoiceFactory extends Factory
{
    protected $model = SubscriptionInvoice::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'invoice_id' => null,
            'issue_at' => now()->toDateString(),
            'currency' => 'EUR',
            'amount_minor' => 1999,
            'recognized_minor' => 1999,
            'state' => 'paid',
        ];
    }

    public function forSubscription(int $subscriptionId): static
    {
        return $this->state(['subscription_id' => $subscriptionId]);
    }

    public function forInvoice(int $invoiceId): static
    {
        return $this->state(['invoice_id' => $invoiceId]);
    }

    public function amount(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function pending(): static
    {
        return $this->state(['state' => 'pending', 'recognized_minor' => 0]);
    }

    public function paid(): static
    {
        return $this->state(['state' => 'paid', 'recognized_minor' => $this->faker->numberBetween(1000, 100000)]);
    }

    public function failed(): static
    {
        return $this->state(['state' => 'failed']);
    }
}
