<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\BankableProxy;

class FakeBankableProxy extends FakeModel
{
    use BankableProxy;

    protected $table = 'fake_bankables';

    protected $attributes = [
        'iban' => 'FR1420041010050500013M02606',
        'bic' => 'CRLYFRPP',
        'currency' => 'EUR',
        'is_default' => true,
    ];
}
