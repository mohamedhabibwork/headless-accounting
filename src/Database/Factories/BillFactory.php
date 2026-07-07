<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Bill;
use Headless\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'vendor_id' => Vendor::factory(),
            'number' => 'BILL-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'currency' => 'EUR',
            'fx_currency' => null,
            'fx_rate' => null,
            'subtotal_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 0,
            'paid_minor' => 0,
            'balance_minor' => 0,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'state' => Bill::STATE_DRAFT,
            'notes' => null,
            'metadata' => null,
        ];
    }

    public function forVendor(int $vendorId): static
    {
        return $this->state(['vendor_id' => $vendorId]);
    }

    public function draft(): static
    {
        return $this->state(['state' => Bill::STATE_DRAFT]);
    }

    public function received(): static
    {
        return $this->state(['state' => Bill::STATE_RECEIVED]);
    }

    public function paid(): static
    {
        return $this->state([
            'state' => Bill::STATE_PAID,
            'paid_minor' => $this->faker->numberBetween(1000, 1000000),
            'balance_minor' => 0,
        ]);
    }

    public function partial(): static
    {
        return $this->state(['state' => Bill::STATE_PARTIAL]);
    }

    public function void(): static
    {
        return $this->state(['state' => Bill::STATE_VOID]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => Bill::STATE_CANCELLED]);
    }

    public function overdue(): static
    {
        return $this->state([
            'bill_date' => now()->subDays(60)->toDateString(),
            'due_date' => now()->subDays(30)->toDateString(),
        ]);
    }

    public function inCurrency(string $currency, ?float $fxRate = null): static
    {
        return $this->state([
            'currency' => $currency,
            'fx_currency' => $currency === 'EUR' ? null : 'EUR',
            'fx_rate' => $fxRate,
        ]);
    }

    public function withTotals(int $subtotal, int $tax, int $total): static
    {
        return $this->state([
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'balance_minor' => $total,
        ]);
    }

    public function dueInDays(int $days): static
    {
        return $this->state(['due_date' => now()->addDays($days)->toDateString()]);
    }

    public function metadata(array $meta): static
    {
        return $this->state(['metadata' => $meta]);
    }
}
