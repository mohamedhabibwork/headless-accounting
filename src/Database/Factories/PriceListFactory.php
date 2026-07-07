<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        return [
            'name' => ucwords($this->faker->unique()->words(3, true)),
            'code' => $this->faker->unique()->slug(3),
            'scope' => 'channel',
            'scope_ref' => 'web',
            'currency' => 'EUR',
            'valid_from' => null,
            'valid_until' => null,
            'priority' => 100,
            'active' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function forChannel(string $channel): static
    {
        return $this->state([
            'scope' => 'channel',
            'scope_ref' => $channel,
        ]);
    }

    public function forGroup(string $customerGroup): static
    {
        return $this->state([
            'scope' => 'customer_group',
            'scope_ref' => $customerGroup,
        ]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function priority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function validBetween(string $from, string $until): static
    {
        return $this->state([
            'valid_from' => $from,
            'valid_until' => $until,
        ]);
    }
}
