<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\EmployeeVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeVehicleFactory extends Factory
{
    protected $model = EmployeeVehicle::class;

    public function definition(): array
    {
        return [
            'employee_id' => null,
            'plate' => strtoupper($this->faker->unique()->bothify('??-###-??')),
            'description' => $this->faker->optional(0.5)->words(3, true),
            'mileage_rate_minor_per_km' => 0.32,
        ];
    }

    public function forEmployee(int $employeeId): static
    {
        return $this->state(['employee_id' => $employeeId]);
    }

    public function plate(string $plate): static
    {
        return $this->state(['plate' => $plate]);
    }

    public function rateMinorPerKm(float $rate): static
    {
        return $this->state(['mileage_rate_minor_per_km' => $rate]);
    }

    public function description(string $description): static
    {
        return $this->state(['description' => $description]);
    }
}
