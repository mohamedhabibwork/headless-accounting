<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Contracts\TaxRateResolver;

class FakeTaxRateResolver implements TaxRateResolver
{
    public bool $configured = true;

    public function resolve(array $context): iterable
    {
        return [];
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
