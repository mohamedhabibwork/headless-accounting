<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasEmployee;
use Headless\Accounting\Contracts\EmployeeLinkable;

class FakeEmployeeOwner extends FakeModel implements EmployeeLinkable
{
    use HasEmployee;

    protected $table = 'fake_employee_owners';
}
