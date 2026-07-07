<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasCustomer;
use Headless\Accounting\Contracts\CustomerOwner;

class FakeCustomerOwner extends FakeModel implements CustomerOwner
{
    use HasCustomer;

    protected $table = 'fake_customer_owners';
}
