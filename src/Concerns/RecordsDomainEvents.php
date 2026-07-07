<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Concerns\RecordsEvents;
use Headless\Accounting\Models\EventStream;
use Illuminate\Database\Eloquent\Model;

/**
 * RecordsDomainEvents — companion to {@see RecordsEvents}
 * for host-side models that aren't part of the package but still want
 * to push events into the polymorphic `ha_event_stream` table for
 * downstream consumers / audit trail.
 *
 * @mixin Model
 */
trait RecordsDomainEvents
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
