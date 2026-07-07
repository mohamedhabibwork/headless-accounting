<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\ForChannel;

class FakeChannelModel extends FakeModel
{
    use ForChannel;

    protected $table = 'fake_channel_models';
}
