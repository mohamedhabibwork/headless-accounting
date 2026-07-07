<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Listeners\NotifyRecipientResolver;

/**
 * NotificationRecipient — host-side contract that bridges the package
 * to the host's notification system.
 *
 * The package never sends emails / SMS directly — it delegates to
 * whatever the host already uses (Notification classes, queued
 * mailables, third-party services like Postmark / Twilio, …) by
 * calling `notify()` / `notifyNow()` on the returned recipient.
 *
 * Either:
 *   1. Implement this interface on your own NotificationRecipient class
 *      that holds a reference to the host's User model, or
 *   2. Use the {@see NotifyRecipientResolver}
 *      listener shipped with this package to resolve a recipient from
 *      a polymorphic owner reference.
 */
interface NotificationRecipient
{
    /**
     * The display name for the recipient (used in templating and "to"
     * headers when no name is set by the notification itself).
     */
    public function displayName(): string;

    /**
     * All addresses to which the recipient may be notified.
     * Hosts may return email addresses, phone numbers, push tokens,
     * or anything else their notification channel understands.
     *
     * @return iterable<string>
     */
    public function notificationAddresses(): iterable;

    /**
     * Optional locale preference (RFC 5646) — overrides the channel
     * locale when present.
     */
    public function preferredLocale(): ?string;
}
