<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\TaxZone;
use Headless\Accounting\Models\TaxZoneMember;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxZoneMemberFactory extends Factory
{
    protected $model = TaxZoneMember::class;

    public function definition(): array
    {
        return [
            'zone_id' => TaxZone::factory(),
            'country_code' => $this->faker->randomElement(['FR', 'DE', 'IT', 'ES', 'BE', 'NL', 'GB']),
            'region' => null,
            'postal_code_pattern' => null,
            'operator' => 'or',
        ];
    }

    public function forZone(int $zoneId): static
    {
        return $this->state(['zone_id' => $zoneId]);
    }

    public function country(string $countryCode): static
    {
        return $this->state(['country_code' => $countryCode]);
    }

    public function region(string $region): static
    {
        return $this->state(['region' => $region]);
    }

    public function postalPattern(string $pattern): static
    {
        return $this->state(['postal_code_pattern' => $pattern]);
    }

    public function andOperator(): static
    {
        return $this->state(['operator' => 'and']);
    }

    public function orOperator(): static
    {
        return $this->state(['operator' => 'or']);
    }
}
