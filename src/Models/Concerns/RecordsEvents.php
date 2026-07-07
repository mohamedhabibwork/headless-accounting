<?php

declare(strict_types=1);

namespace Headless\Accounting\Models\Concerns;

use Headless\Accounting\Models\EventStream;

/**
 * Records domain events on a subject row in `ha_event_stream`.
 * Used by every aggregate root (Order, Invoice, Payment, …).
 */
trait RecordsEvents
{
    public function recordEvent(string $type, array $payload = []): EventStream
    {
        return EventStream::record($this, $type, $payload);
    }

    public function events()
    {
        return $this->morphMany(EventStream::class, 'subject')->orderBy('occurred_at');
    }
}
