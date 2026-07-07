<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasPayments;

class FakeHasPayments extends FakeModel
{
    use HasPayments;

    protected $table = 'fake_with_payments';
}
