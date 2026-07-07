<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\ImplementsPayable;
use Headless\Accounting\Contracts\Payable;

class FakePayable extends FakeModel implements Payable
{
    use ImplementsPayable;

    protected $table = 'fake_payables';
}
