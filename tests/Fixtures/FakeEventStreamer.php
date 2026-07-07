<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\RecordsDomainEvents;

class FakeEventStreamer extends FakeModel
{
    use RecordsDomainEvents;

    protected $table = 'fake_event_streamers';
}
