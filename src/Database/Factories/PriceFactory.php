<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Price;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceFactory extends Factory
{
    protected $model = Price::class;

    public function definition(): array
    {
        return [
            'price_list_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'amount_minor' => 1999,
            'currency' => 'EUR',
            'compare_at_minor' => null,
            'min_quantity' => 1,
            'tax_inclusive' => false,
            'valid_from' => null,
            'valid_until' => null,
        ];
    }

    public function forPriceList(int $priceListId): static
    {
        return $this->state(['price_list_id' => $priceListId]);
    }

    public function forSubject(string $type, int $id): static
    {
        return $this->state([
            'subject_type' => $type,
            'subject_id' => $id,
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

    public function withCompareAt(int $amountMinor): static
    {
        return $this->state(['compare_at_minor' => $amountMinor]);
    }

    public function onSale(int $salePrice, int $compareAt): static
    {
        return $this->state([
            'amount_minor' => $salePrice,
            'compare_at_minor' => $compareAt,
        ]);
    }

    public function tier(float $minQuantity): static
    {
        return $this->state(['min_quantity' => $minQuantity]);
    }

    public function taxInclusive(): static
    {
        return $this->state(['tax_inclusive' => true]);
    }

    public function validFrom(string $date): static
    {
        return $this->state(['valid_from' => $date]);
    }

    public function validBetween(string $from, string $until): static
    {
        return $this->state([
            'valid_from' => $from,
            'valid_until' => $until,
        ]);
    }
}
