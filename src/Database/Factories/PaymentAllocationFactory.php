<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'payment_id' => Payment::factory(),
            'target_type' => null,
            'target_id' => null,
            'currency' => 'EUR',
            'amount_minor' => 1000,
            'fx_rate' => 1.0,
            'allocated_at' => now()->toDateString(),
        ];
    }

    public function forPayment(int $paymentId): static
    {
        return $this->state(['payment_id' => $paymentId]);
    }

    public function forTarget(string $type, int $id): static
    {
        return $this->state([
            'target_type' => $type,
            'target_id' => $id,
        ]);
    }

    public function amount(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function fxRate(float $fxRate): static
    {
        return $this->state(['fx_rate' => $fxRate]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
