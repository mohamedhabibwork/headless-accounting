<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Discounts\Drivers\AmountDiscount;
use Headless\Accounting\Discounts\Drivers\PercentageDiscount;
use Headless\Accounting\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'code' => null,
            'type' => PercentageDiscount::class,
            'active' => true,
            'stackable' => false,
            'priority' => 100,
            'config' => ['percent' => 10],
            'starts_at' => null,
            'ends_at' => null,
            'channel_code' => null,
            'owner_type' => null,
            'owner_id' => null,
        ];
    }

    public function percentage(float $percent = 10): static
    {
        return $this->state([
            'type' => PercentageDiscount::class,
            'config' => ['percent' => $percent],
        ]);
    }

    public function amount(int $amountMinor = 500, string $currency = 'EUR'): static
    {
        return $this->state([
            'type' => AmountDiscount::class,
            'config' => ['amount_minor' => $amountMinor, 'currency' => $currency],
        ]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function coupon(string $code): static
    {
        return $this->state([
            'code' => $code,
            'name' => 'Coupon '.$code,
        ]);
    }

    public function active(): static
    {
        return $this->state(['active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function stackable(): static
    {
        return $this->state(['stackable' => true]);
    }

    public function priority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function onChannel(string $channel): static
    {
        return $this->state(['channel_code' => $channel]);
    }

    public function startingAt(string $date): static
    {
        return $this->state(['starts_at' => $date]);
    }

    public function endingAt(string $date): static
    {
        return $this->state(['ends_at' => $date]);
    }

    public function validBetween(string $from, string $until): static
    {
        return $this->state([
            'starts_at' => $from,
            'ends_at' => $until,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(['ends_at' => now()->subDay()]);
    }

    public function forOwner(string $type, int $id): static
    {
        return $this->state([
            'owner_type' => $type,
            'owner_id' => $id,
        ]);
    }
}
