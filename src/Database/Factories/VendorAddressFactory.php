<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Vendor;
use Headless\Accounting\Models\VendorAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorAddressFactory extends Factory
{
    protected $model = VendorAddress::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'type' => 'billing',
            'address_lines' => [$this->faker->streetAddress()],
            'city' => $this->faker->city(),
            'region' => $this->faker->optional(0.5)->state(),
            'country_code' => $this->faker->randomElement(['FR', 'DE', 'IT', 'GB', 'NL']),
            'postal_code' => $this->faker->postcode(),
        ];
    }

    public function forVendor(int $vendorId): static
    {
        return $this->state(['vendor_id' => $vendorId]);
    }

    public function billing(): static
    {
        return $this->state(['type' => 'billing']);
    }

    public function shipping(): static
    {
        return $this->state(['type' => 'shipping']);
    }

    public function inCountry(string $countryCode): static
    {
        return $this->state(['country_code' => $countryCode]);
    }
}
