<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Accounting\AuditEvent;
use Headless\Accounting\Models\ChangeHistory;

/**
 * Auditable — host-side contract for any object that participates in
 * the package's audit trail (the polymorphic {@see ChangeHistory}
 * table).
 *
 * Implementations only need to expose a stable, type-safe identifier
 * (the model's primary key is fine) and a logical "who" — usually the
 * authenticated user, or a system actor for background processes.
 *
 * The package's built-in {@see AuditEvent}
 * already understands any Eloquent model that implements this interface
 * — see `AuditEvent::record()`.
 */
interface Auditable
{
    /** A stable, human-readable identifier (e.g. 'ORD-2026-000123'). */
    public function auditIdentifier(): string;

    /**
     * The actor responsible for the change. May be a User, an
     * integration account, or `null` for system actions.
     */
    public function auditActor(): mixed;

    /**
     * Free-form context (channel code, source IP, locale, …) that
     * will be persisted alongside the audit row.
     *
     * @return array<string,mixed>
     */
    public function auditContext(): array;
}
