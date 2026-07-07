<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\BelongsToTenantCompany;

class FakeTenant extends FakeModel
{
    use BelongsToTenantCompany;

    protected $table = 'fake_tenants';
}
