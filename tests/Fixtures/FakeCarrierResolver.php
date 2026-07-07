<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Contracts\CarrierResolver;

class FakeCarrierResolver implements CarrierResolver
{
    public bool $configured = true;

    public function quote(array $parcel): iterable
    {
        return [
            [
                'carrier' => 'fake-courier',
                'service' => 'standard',
                'cost_minor' => 699,
                'currency' => $parcel['currency'] ?? 'EUR',
                'eta_days_to' => 3,
                'tracking_url_template' => 'https://example.com/track/{tracking}',
            ],
        ];
    }

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
