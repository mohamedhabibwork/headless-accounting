<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            Account::TYPE_ASSET,
            Account::TYPE_LIABILITY,
            Account::TYPE_EQUITY,
            Account::TYPE_REVENUE,
            Account::TYPE_EXPENSE,
        ]);

        return [
            'code' => $this->faker->unique()->numerify('####'),
            'name' => ucwords($this->faker->words(3, true)).' Account',
            'type' => $type,
            'parent_id' => null,
            'currency' => 'EUR',
            'active' => true,
        ];
    }

    public function asset(): static
    {
        return $this->state(['type' => Account::TYPE_ASSET]);
    }

    public function liability(): static
    {
        return $this->state(['type' => Account::TYPE_LIABILITY]);
    }

    public function equity(): static
    {
        return $this->state(['type' => Account::TYPE_EQUITY]);
    }

    public function revenue(): static
    {
        return $this->state(['type' => Account::TYPE_REVENUE]);
    }

    public function expense(): static
    {
        return $this->state(['type' => Account::TYPE_EXPENSE]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function parent(int $parentId): static
    {
        return $this->state(['parent_id' => $parentId]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
