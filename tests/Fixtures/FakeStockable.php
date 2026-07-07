<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\ImplementsStockable;
use Headless\Accounting\Contracts\Stockable;

class FakeStockable extends FakeModel implements Stockable
{
    use ImplementsStockable;

    protected $table = 'fake_stockables';
}
