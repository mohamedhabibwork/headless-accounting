<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => $this->faker->unique()->words(2, true),
            'client_id' => 'cli_'.bin2hex(random_bytes(8)),
            'secret_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'scopes' => ['read', 'write'],
            'active' => true,
            'last_used_at' => null,
        ];
    }

    public function forCompany(int $companyId): static
    {
        return $this->state(['company_id' => $companyId]);
    }

    public function active(): static
    {
        return $this->state(['active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function scopes(array $scopes): static
    {
        return $this->state(['scopes' => $scopes]);
    }

    public function readOnly(): static
    {
        return $this->state(['scopes' => ['read']]);
    }

    public function name(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
