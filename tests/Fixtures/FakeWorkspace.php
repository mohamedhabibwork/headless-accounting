<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasManyCustomers;
use Headless\Accounting\Concerns\HasVendor;

class FakeWorkspace extends FakeModel
{
    use HasManyCustomers, HasVendor;

    protected $table = 'fake_workspaces';
}
