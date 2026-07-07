<?php

declare(strict_types=1);

namespace Headless\Accounting\Tax;

use Headless\Accounting\Currency\Money;

final class TaxLine
{
    public function __construct(
        public readonly int $rateId,
        public readonly string $rateName,
        public readonly float $percent,
        public readonly Money $amount,
        public readonly bool $compound,
    ) {}
}
