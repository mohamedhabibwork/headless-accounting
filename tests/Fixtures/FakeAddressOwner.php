<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasAddresses;

class FakeAddressOwner extends FakeModel
{
    use HasAddresses;

    protected $table = 'fake_address_owners';
}
