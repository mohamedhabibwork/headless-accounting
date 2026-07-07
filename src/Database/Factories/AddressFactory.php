<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'owner_type' => null,
            'owner_id' => null,
            'type' => $this->faker->randomElement(['shipping', 'billing', 'both']),
            'company' => null,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'line1' => $this->faker->streetAddress(),
            'line2' => $this->faker->optional(0.3)->secondaryAddress(),
            'city' => $this->faker->city(),
            'region' => $this->faker->optional(0.5)->state(),
            'postal_code' => $this->faker->postcode(),
            'country_code' => $this->faker->randomElement(['FR', 'DE', 'IT', 'ES', 'GB', 'US', 'NL', 'BE']),
            'phone' => $this->faker->e164PhoneNumber(),
            'is_default' => false,
        ];
    }

    public function shipping(): static
    {
        return $this->state(['type' => 'shipping']);
    }

    public function billing(): static
    {
        return $this->state(['type' => 'billing']);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function forCountry(string $countryCode): static
    {
        return $this->state(['country_code' => $countryCode]);
    }

    public function forOwner(string $type, int $id): static
    {
        return $this->state(['owner_type' => $type, 'owner_id' => $id]);
    }
}
