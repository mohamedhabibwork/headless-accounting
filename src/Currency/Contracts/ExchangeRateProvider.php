<?php

declare(strict_types=1);

namespace Headless\Accounting\Currency\Contracts;

use Carbon\CarbonImmutable;

interface ExchangeRateProvider
{
    /**
     * Returns the exchange rate to multiply a *base* currency amount by to
     * obtain an amount in *quote* currency. e.g. EUR → USD returns 1.08.
     *
     * @return array<string,float> Map of currency code => rate, with at
     *                             least the requested quote currency present.
     */
    public function rates(string $base, array $quotes, CarbonImmutable $at): array;
}
