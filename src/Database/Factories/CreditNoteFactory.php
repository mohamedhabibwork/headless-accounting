<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\CreditNote;
use Headless\Accounting\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            'number' => 'CN-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'invoice_id' => Invoice::factory(),
            'amount_minor' => 1000,
            'currency' => 'EUR',
            'reason' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function forInvoice(int $invoiceId): static
    {
        return $this->state(['invoice_id' => $invoiceId]);
    }

    public function amount(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}
