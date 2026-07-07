<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'number' => 'PAY-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'payable_type' => null,
            'payable_id' => null,
            'currency' => 'EUR',
            'amount_minor' => 1999,
            'driver' => 'stripe',
            'method' => 'card',
            'state' => Payment::STATE_PENDING,
            'provider_id' => strtoupper($this->faker->bothify('pi_########???')),
            'provider_state' => null,
            'provider_response' => null,
            'authorized_at' => null,
            'captured_at' => null,
            'refunded_at' => null,
            'voided_at' => null,
            'customer_id' => null,
        ];
    }

    public function forPayable(string $type, int $id): static
    {
        return $this->state([
            'payable_type' => $type,
            'payable_id' => $id,
        ]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function driver(string $driver, ?string $method = null): static
    {
        return $this->state([
            'driver' => $driver,
            'method' => $method ?? 'card',
        ]);
    }

    public function stripe(): static
    {
        return $this->state(['driver' => 'stripe', 'method' => 'card']);
    }

    public function bankTransfer(): static
    {
        return $this->state(['driver' => 'bank_transfer', 'method' => 'sepa']);
    }

    public function paypal(): static
    {
        return $this->state(['driver' => 'paypal']);
    }

    public function amount(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function pending(): static
    {
        return $this->state(['state' => Payment::STATE_PENDING]);
    }

    public function authorized(): static
    {
        return $this->state([
            'state' => Payment::STATE_AUTHORIZED,
            'authorized_at' => now(),
        ]);
    }

    public function captured(): static
    {
        return $this->state([
            'state' => Payment::STATE_CAPTURED,
            'authorized_at' => now()->subMinute(),
            'captured_at' => now(),
        ]);
    }

    public function partiallyRefunded(): static
    {
        return $this->state(['state' => Payment::STATE_PARTIAL_REFUNDED]);
    }

    public function refunded(): static
    {
        return $this->state([
            'state' => Payment::STATE_REFUNDED,
            'refunded_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state([
            'state' => Payment::STATE_VOIDED,
            'voided_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['state' => Payment::STATE_FAILED]);
    }
}
