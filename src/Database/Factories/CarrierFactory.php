<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Carrier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarrierFactory extends Factory
{
    protected $model = Carrier::class;

    public function definition(): array
    {
        $codes = ['dhl', 'ups', 'fedex', 'gls', 'dpd', 'colissimo'];
        $code = $this->faker->randomElement($codes);
        $names = [
            'dhl' => 'DHL Express',
            'ups' => 'UPS',
            'fedex' => 'FedEx',
            'gls' => 'GLS',
            'dpd' => 'DPD',
            'colissimo' => 'Colissimo',
        ];

        return [
            'code' => $code,
            'name' => $names[$code],
            'tracking_url_template' => "https://track.example.com/{$code}/{tracking}",
            'service_levels' => [
                ['code' => 'express', 'name' => 'Express', 'eta_days_from' => 1, 'eta_days_to' => 2],
                ['code' => 'economy', 'name' => 'Economy', 'eta_days_from' => 3, 'eta_days_to' => 5],
            ],
            'credentials' => ['api_key' => 'test-key'],
            'sandbox' => true,
            'active' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function production(bool $sandbox = false): static
    {
        return $this->state(['sandbox' => $sandbox]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function trackingTemplate(string $template): static
    {
        return $this->state(['tracking_url_template' => $template]);
    }
}
