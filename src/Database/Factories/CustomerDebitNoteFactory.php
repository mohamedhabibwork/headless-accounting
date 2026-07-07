<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\CustomerDebitNote;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerDebitNoteFactory extends Factory
{
    protected $model = CustomerDebitNote::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'customer_id' => Customer::factory(),
            'invoice_id' => null,
            'number' => 'DBN-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'currency' => 'EUR',
            'amount_minor' => 1000,
            'reason' => $this->faker->optional(0.5)->sentence(),
            'state' => 'draft',
            'issued_at' => now()->toDateString(),
        ];
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function forInvoice(int $invoiceId): static
    {
        return $this->state(['invoice_id' => $invoiceId]);
    }

    public function issued(): static
    {
        return $this->state(['state' => 'issued']);
    }

    public function paid(): static
    {
        return $this->state(['state' => 'paid']);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => 'cancelled']);
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
