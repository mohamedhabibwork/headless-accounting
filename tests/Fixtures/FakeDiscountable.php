<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\ImplementsDiscountable;
use Headless\Accounting\Contracts\Discountable;

class FakeDiscountable extends FakeModel implements Discountable
{
    use ImplementsDiscountable;

    protected $table = 'fake_discountables';
}
