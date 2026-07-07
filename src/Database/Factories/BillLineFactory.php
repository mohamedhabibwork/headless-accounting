<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Bill;
use Headless\Accounting\Models\BillLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillLineFactory extends Factory
{
    protected $model = BillLine::class;

    public function definition(): array
    {
        return [
            'bill_id' => Bill::factory(),
            'product_id' => null,
            'description' => ucfirst($this->faker->words(4, true)),
            'quantity' => 1,
            'unit_cost_minor' => 1000,
            'currency' => 'EUR',
            'tax_percent' => 20,
            'tax_rate_id' => null,
            'account_id' => null,
        ];
    }

    public function forBill(int $billId): static
    {
        return $this->state(['bill_id' => $billId]);
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(['quantity' => $quantity]);
    }

    public function unitCost(int $unitCostMinor): static
    {
        return $this->state(['unit_cost_minor' => $unitCostMinor]);
    }

    public function withTaxRate(int $taxRateId, float $percent): static
    {
        return $this->state([
            'tax_rate_id' => $taxRateId,
            'tax_percent' => $percent,
        ]);
    }

    public function forAccount(int $accountId): static
    {
        return $this->state(['account_id' => $accountId]);
    }

    public function description(string $description): static
    {
        return $this->state(['description' => $description]);
    }
}
