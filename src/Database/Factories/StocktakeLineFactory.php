<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\StocktakeLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class StocktakeLineFactory extends Factory
{
    protected $model = StocktakeLine::class;

    public function definition(): array
    {
        return [
            'stocktake_id' => null,
            'variant_id' => null,
            'bin_id' => null,
            'system_quantity' => 10,
            'counted_quantity' => null,
            'variance' => 0,
            'unit_cost_minor' => 0,
            'variance_value_minor' => 0,
            'currency' => 'EUR',
            'state' => StocktakeLine::STATE_PENDING,
            'count_round' => 1,
            'reason' => null,
            'counter_id' => null,
            'counted_at' => null,
        ];
    }

    public function counted(int $quantity, ?int $unitCostMinor = null): static
    {
        return $this->state(function (array $attrs) use ($quantity, $unitCostMinor) {
            $system = (int) $attrs['system_quantity'];
            $variance = $quantity - $system;
            $cost = (int) ($attrs['unit_cost_minor'] ?? $unitCostMinor ?? 0);

            return [
                'counted_quantity' => $quantity,
                'variance' => $variance,
                'variance_value_minor' => $variance * $cost,
                'state' => StocktakeLine::STATE_COUNTED,
                'counted_at' => now(),
            ];
        });
    }

    public function recount(int $quantity): static
    {
        return $this->state([
            'count_round' => 2,
            'counted_quantity' => $quantity,
            'state' => StocktakeLine::STATE_RECOUNT,
            'counted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(['state' => StocktakeLine::STATE_APPROVED]);
    }

    public function rejected(): static
    {
        return $this->state(['state' => StocktakeLine::STATE_REJECTED]);
    }

    public function reason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }

    public function countedBy(int $employeeId): static
    {
        return $this->state(['counter_id' => $employeeId]);
    }
}
