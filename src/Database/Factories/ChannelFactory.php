<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->randomElement(['web', 'shop', 'wholesale', 'mobile']),
            'name' => ucfirst($this->faker->randomElement(['Web', 'Shop', 'Wholesale', 'Mobile'])),
            'currency' => 'EUR',
            'locale' => 'en',
            'tax_zone_code' => 'eu',
            'tax_inclusive' => false,
            'active' => true,
            'config' => null,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function web(): static
    {
        return $this->state(['code' => 'web', 'name' => 'Web', 'currency' => 'EUR', 'locale' => 'en']);
    }

    public function shop(): static
    {
        return $this->state(['code' => 'shop', 'name' => 'Shop']);
    }

    public function wholesale(): static
    {
        return $this->state(['code' => 'wholesale', 'name' => 'Wholesale']);
    }

    public function mobile(): static
    {
        return $this->state(['code' => 'mobile', 'name' => 'Mobile']);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }

    public function locale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }

    public function taxInclusive(): static
    {
        return $this->state(['tax_inclusive' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
