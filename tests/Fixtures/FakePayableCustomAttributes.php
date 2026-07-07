<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\ImplementsPayable;
use Headless\Accounting\Contracts\Payable;

class FakePayableCustomAttributes extends FakeModel implements Payable
{
    use ImplementsPayable;

    protected $table = 'fake_payables';

    public array $attributesLocal = [
        'payableTotalAttribute' => 'amount',
        'payablePaidAttribute' => 'settled',
        'payableCurrencyAttribute' => 'iso_currency',
    ];
}
