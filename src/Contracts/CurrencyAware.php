<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

interface CurrencyAware
{
    public function currency(): string;

    public function amountMinor(): int;
}
