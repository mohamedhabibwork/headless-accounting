<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ExpenseClaim;
use Headless\Accounting\Models\ExpenseLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseLineFactory extends Factory
{
    protected $model = ExpenseLine::class;

    public function definition(): array
    {
        return [
            'claim_id' => ExpenseClaim::factory(),
            'account_id' => null,
            'date' => now()->subDays($this->faker->numberBetween(1, 30))->toDateString(),
            'description' => $this->faker->sentence(),
            'amount_minor' => $this->faker->numberBetween(100, 50000),
            'currency' => 'EUR',
            'tax_percent' => 20,
            'tax_rate_id' => null,
            'mileage_km' => null,
            'vehicle_id' => null,
            'receipt_url' => null,
        ];
    }

    public function forClaim(int $claimId): static
    {
        return $this->state(['claim_id' => $claimId]);
    }

    public function forAccount(int $accountId): static
    {
        return $this->state(['account_id' => $accountId]);
    }

    public function withTax(int $taxRateId, float $percent): static
    {
        return $this->state([
            'tax_rate_id' => $taxRateId,
            'tax_percent' => $percent,
        ]);
    }

    public function mileage(int $kilometers, int $vehicleId, int $rateMinorPerKm = 32): static
    {
        return $this->state([
            'mileage_km' => $kilometers,
            'vehicle_id' => $vehicleId,
            'amount_minor' => (int) round($kilometers * $rateMinorPerKm),
        ]);
    }

    public function amount(int $amountMinor): static
    {
        return $this->state(['amount_minor' => $amountMinor]);
    }

    public function description(string $description): static
    {
        return $this->state(['description' => $description]);
    }

    public function withReceipt(string $url): static
    {
        return $this->state(['receipt_url' => $url]);
    }
}
