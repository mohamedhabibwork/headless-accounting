<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Batch;

class BatchExpired extends Event
{
    public function __construct(public readonly Batch $batch) {}
}
