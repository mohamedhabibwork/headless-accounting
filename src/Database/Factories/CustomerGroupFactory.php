<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    public function definition(): array
    {
        $name = ucwords($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'code' => strtoupper($this->faker->unique()->bothify('??_##??')),
            'description' => $this->faker->optional(0.5)->sentence(),
            'tax_exempt' => false,
        ];
    }

    public function taxExempt(): static
    {
        return $this->state(['tax_exempt' => true]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function vip(): static
    {
        return $this->state(['name' => 'VIP', 'code' => 'VIP', 'description' => 'VIP customers']);
    }

    public function wholesale(): static
    {
        return $this->state(['name' => 'Wholesale', 'code' => 'WHOLESALE']);
    }
}
