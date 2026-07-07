<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasChangeHistory;

class FakeChangeHistory extends FakeModel
{
    use HasChangeHistory;

    protected $table = 'fake_change_histories';
}
