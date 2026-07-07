<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountUsageFactory extends Factory
{
    protected $model = DiscountUsage::class;

    public function definition(): array
    {
        return [
            'discount_id' => Discount::factory(),
            'customer_id' => Customer::factory(),
            'source_type' => null,
            'source_id' => null,
            'amount_minor' => 100,
            'currency' => 'EUR',
            'used_at' => now(),
        ];
    }

    public function forDiscount(int $discountId): static
    {
        return $this->state(['discount_id' => $discountId]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(['customer_id' => $customerId]);
    }

    public function forSource(string $type, int $id): static
    {
        return $this->state([
            'source_type' => $type,
            'source_id' => $id,
        ]);
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
