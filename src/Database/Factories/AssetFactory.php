<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $purchaseYear = $this->faker->numberBetween((int) date('Y') - 5, (int) date('Y'));

        return [
            'company_id' => null,
            'category_id' => AssetCategory::factory(),
            'code' => 'AST-'.strtoupper($this->faker->unique()->bothify('##??##')),
            'name' => ucwords($this->faker->unique()->words(3, true)),
            'description' => $this->faker->optional(0.5)->sentence(),
            'serial_number' => strtoupper($this->faker->bothify('SN##??##??')),
            'purchase_date' => sprintf('%04d-%02d-%02d', $purchaseYear, $this->faker->numberBetween(1, 12), $this->faker->numberBetween(1, 28)),
            'in_service_date' => sprintf('%04d-%02d-%02d', $purchaseYear, $this->faker->numberBetween(1, 12), $this->faker->numberBetween(1, 28)),
            'disposed_at' => null,
            'currency' => 'EUR',
            'cost_minor' => 100000,
            'residual_minor' => 5000,
            'accumulated_depreciation_minor' => 0,
            'depreciation_method' => $this->faker->randomElement(['straight_line', 'declining_balance', 'units_of_production']),
            'useful_life_years' => 5,
            'depreciation_rate_pct' => 20.0,
            'location_id' => null,
            'custodian_id' => null,
            'state' => 'active',
            'chart_account_id' => null,
        ];
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(['category_id' => $categoryId]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function active(): static
    {
        return $this->state(['state' => 'active']);
    }

    public function disposed(): static
    {
        return $this->state([
            'state' => 'disposed',
            'disposed_at' => now()->toDateString(),
        ]);
    }

    public function fullyDepreciated(): static
    {
        return $this->state(function (array $attrs) {
            $cost = (int) $attrs['cost_minor'];
            $residual = (int) $attrs['residual_minor'];

            return ['accumulated_depreciation_minor' => $cost - $residual];
        });
    }

    public function inService(?string $date = null): static
    {
        return $this->state(['in_service_date' => $date ?? now()->toDateString()]);
    }

    public function straightLine(int $years): static
    {
        return $this->state([
            'depreciation_method' => 'straight_line',
            'useful_life_years' => $years,
            'depreciation_rate_pct' => 100 / max(1, $years),
        ]);
    }

    public function cost(int $costMinor, int $residualMinor = 0): static
    {
        return $this->state([
            'cost_minor' => $costMinor,
            'residual_minor' => $residualMinor,
        ]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(['currency' => $currency]);
    }
}
