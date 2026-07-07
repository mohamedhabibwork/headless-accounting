<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Accounting\AuditEvent;
use Headless\Accounting\Models\ChangeHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasChangeHistory — drop-in trait for host-side models that should
 * be audited through the package's polymorphic `ChangeHistory` log.
 *
 * The trait wires the relation and exposes a record() helper for
 * convenience. Use it together with
 * {@see AuditEvent} when committing a
 * delta from a domain action.
 *
 * @mixin Model
 */
trait HasChangeHistory
{
    /** @return MorphMany<ChangeHistory> */
    public function changeHistory(): MorphMany
    {
        return $this->morphMany(
            ChangeHistory::class,
            'subject'
        );
    }

    public function recordHistory(string $action, array $changes = [], mixed $actor = null): ChangeHistory
    {
        return $this->changeHistory()->create([
            'action' => $action,
            'changes' => $changes,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey() ? (string) $actor->getKey() : null,
            'occurred_at' => now(),
        ]);
    }
}
