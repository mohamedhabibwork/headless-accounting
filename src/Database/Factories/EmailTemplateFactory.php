<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('##_??##')),
            'subject' => $this->faker->sentence(),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
            'placeholders' => ['customer_name', 'order_number'],
            'active' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function subject(string $subject): static
    {
        return $this->state(['subject' => $subject]);
    }

    public function body(string $body): static
    {
        return $this->state(['body' => $body]);
    }

    public function placeholders(array $placeholders): static
    {
        return $this->state(['placeholders' => $placeholders]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
