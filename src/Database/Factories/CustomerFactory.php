<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'owner_type' => null,
            'owner_id' => null,
            'email' => $this->faker->unique()->safeEmail(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'company' => null,
            'vat_id' => null,
            'phone' => $this->faker->e164PhoneNumber(),
            'default_currency' => 'EUR',
            'default_locale' => 'en',
            'email_verified_at' => null,
            'metadata' => null,
        ];
    }

    public function company(?string $name = null): static
    {
        return $this->state([
            'company' => $name ?? $this->faker->company(),
            'vat_id' => strtoupper($this->faker->bothify('??########')),
        ]);
    }

    public function verified(): static
    {
        return $this->state(['email_verified_at' => now()]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['default_currency' => $currency]);
    }

    public function locale(string $locale): static
    {
        return $this->state(['default_locale' => $locale]);
    }
}
