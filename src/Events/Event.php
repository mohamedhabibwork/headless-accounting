<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event — base class shared by all package domain events. They are
 * broadcastable, queueable and serializable out of the box.
 *
 * Domain events are also persisted to `ha_event_stream` by the
 * Aggregate that raises them, so listeners have two channels:
 *   - Laravel event bus   (in-process, queue)
 *   - Event stream table  (audit + outbox)
 */
abstract class Event
{
    use Dispatchable;
}
