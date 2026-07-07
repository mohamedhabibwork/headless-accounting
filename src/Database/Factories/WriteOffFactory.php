<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\WriteOff;
use Illuminate\Database\Eloquent\Factories\Factory;

class WriteOffFactory extends Factory
{
    protected $model = WriteOff::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'source_type' => null,
            'source_id' => null,
            'currency' => 'EUR',
            'amount_minor' => $this->faker->numberBetween(100, 100000),
            'reason' => $this->faker->randomElement(['bad_debt', 'damaged_goods', 'expired', 'obsolete']),
        ];
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

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }

    public function badDebt(): static
    {
        return $this->state(['reason' => 'bad_debt']);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
