<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeDefinitionFactory extends Factory
{
    protected $model = AttributeDefinition::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(2),
            'name' => ucwords($this->faker->words(2, true)),
            'type' => $this->faker->randomElement(['text', 'select', 'multiselect', 'swatch', 'bool']),
            'translatable' => false,
            'config' => null,
        ];
    }

    public function translatable(): static
    {
        return $this->state(['translatable' => true]);
    }

    public function select(?array $options = null): static
    {
        return $this->state([
            'type' => 'select',
            'config' => ['options' => $options ?? ['red', 'green', 'blue', 'yellow']],
        ]);
    }

    public function multiselect(?array $options = null): static
    {
        return $this->state([
            'type' => 'multiselect',
            'config' => ['options' => $options ?? ['cotton', 'polyester', 'wool']],
        ]);
    }

    public function swatch(): static
    {
        return $this->state([
            'type' => 'swatch',
            'config' => ['options' => [
                ['value' => 'red', 'color' => '#FF0000'],
                ['value' => 'blue', 'color' => '#0000FF'],
            ]],
        ]);
    }

    public function ofType(string $type, ?array $config = null): static
    {
        return $this->state(['type' => $type, 'config' => $config]);
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }
}
