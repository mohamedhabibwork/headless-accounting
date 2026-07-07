<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\VendorCreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorCreditNoteFactory extends Factory
{
    protected $model = VendorCreditNote::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'vendor_id' => null,
            'bill_id' => null,
            'number' => 'VCN-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'currency' => 'EUR',
            'amount_minor' => 1000,
            'reason' => $this->faker->optional(0.5)->sentence(),
            'state' => 'draft',
            'issued_at' => now()->toDateString(),
        ];
    }

    public function forVendor(int $vendorId): static
    {
        return $this->state(['vendor_id' => $vendorId]);
    }

    public function forBill(int $billId): static
    {
        return $this->state(['bill_id' => $billId]);
    }

    public function issued(): static
    {
        return $this->state(['state' => 'issued']);
    }

    public function paid(): static
    {
        return $this->state(['state' => 'paid']);
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
