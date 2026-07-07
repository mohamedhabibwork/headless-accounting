<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\PaymentSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentScheduleFactory extends Factory
{
    protected $model = PaymentSchedule::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'source_type' => null,
            'source_id' => null,
            'installment_no' => 1,
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'amount_minor' => 1000,
            'method' => 'bank_transfer',
            'state' => 'pending',
            'paid_at' => null,
            'payment_id' => null,
        ];
    }

    public function forSource(string $type, int $id): static
    {
        return $this->state([
            'source_type' => $type,
            'source_id' => $id,
        ]);
    }

    public function installment(int $number): static
    {
        return $this->state(['installment_no' => $number]);
    }

    public function dueInDays(int $days): static
    {
        return $this->state(['due_date' => now()->addDays($days)->toDateString()]);
    }

    public function overdue(): static
    {
        return $this->state(['due_date' => now()->subDay()->toDateString()]);
    }

    public function paid(): static
    {
        return $this->state([
            'state' => 'paid',
            'paid_at' => now()->toDateString(),
        ]);
    }

    public function method(string $method): static
    {
        return $this->state(['method' => $method]);
    }

    public function amount(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
