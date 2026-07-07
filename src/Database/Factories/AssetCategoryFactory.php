<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetCategoryFactory extends Factory
{
    protected $model = AssetCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('AC-##??')),
            'name' => ucwords($this->faker->unique()->words(2, true)),
            'default_depreciation_method' => 'straight_line',
            'default_useful_life_years' => 5,
            'default_residual_pct' => 5.0,
            'asset_account_id' => null,
            'accumulated_depreciation_account_id' => null,
            'depreciation_expense_account_id' => null,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function straightLine(int $years = 5, float $residualPct = 5.0): static
    {
        return $this->state([
            'default_depreciation_method' => 'straight_line',
            'default_useful_life_years' => $years,
            'default_residual_pct' => $residualPct,
        ]);
    }

    public function decliningBalance(float $rate = 20.0): static
    {
        return $this->state([
            'default_depreciation_method' => 'declining_balance',
            'default_useful_life_years' => 5,
            'default_residual_pct' => 5.0,
        ]);
    }

    public function vehicles(): static
    {
        return $this->state(['code' => 'VEHICLES', 'name' => 'Vehicles', 'default_useful_life_years' => 7]);
    }

    public function equipment(): static
    {
        return $this->state(['code' => 'EQUIP', 'name' => 'Equipment', 'default_useful_life_years' => 5]);
    }

    public function buildings(): static
    {
        return $this->state(['code' => 'BUILD', 'name' => 'Buildings', 'default_useful_life_years' => 25]);
    }

    public function itEquipment(): static
    {
        return $this->state(['code' => 'IT', 'name' => 'IT Equipment', 'default_useful_life_years' => 3]);
    }
}
