<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentRefundFactory extends Factory
{
    protected $model = PaymentRefund::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'amount_minor' => 500,
            'currency' => 'EUR',
            'provider_refund_id' => strtoupper($this->faker->bothify('re_########???')),
            'reason' => $this->faker->optional(0.5)->sentence(),
            'provider_response' => null,
        ];
    }

    public function forPayment(int $paymentId): static
    {
        return $this->state(['payment_id' => $paymentId]);
    }

    public function full(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function partial(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
