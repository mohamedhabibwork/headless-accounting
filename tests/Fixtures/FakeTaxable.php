<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\ImplementsTaxable;
use Headless\Accounting\Contracts\Taxable;

class FakeTaxable extends FakeModel implements Taxable
{
    use ImplementsTaxable;

    protected $table = 'fake_taxables';
}
