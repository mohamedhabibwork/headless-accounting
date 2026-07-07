<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'code' => 'V-'.strtoupper($this->faker->unique()->bothify('??##??')),
            'name' => $this->faker->unique()->company().' '.strtoupper($this->faker->bothify('##??')),
            'legal_name' => null,
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->e164PhoneNumber(),
            'contact_name' => $this->faker->name(),
            'tax_id' => strtoupper($this->faker->bothify('??########')),
            'iban' => strtoupper($this->faker->bothify('??## #### #### #### #### ##')),
            'bic' => strtoupper($this->faker->bothify('?????????#')),
            'default_currency' => 'EUR',
            'default_locale' => 'en',
            'credit_limit_minor' => 100000,
            'currency' => 'EUR',
            'payment_terms_days' => 30,
            'active' => true,
        ];
    }

    public function forCompany(int $companyId): static
    {
        return $this->state(['company_id' => $companyId]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function paymentTerms(int $days): static
    {
        return $this->state(['payment_terms_days' => $days]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state([
            'default_currency' => $currency,
            'currency' => $currency,
        ]);
    }

    public function creditLimit(int $limitMinor): static
    {
        return $this->state(['credit_limit_minor' => $limitMinor]);
    }

    public function locale(string $locale): static
    {
        return $this->state(['default_locale' => $locale]);
    }
}
