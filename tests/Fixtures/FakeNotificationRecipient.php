<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Contracts\NotificationRecipient;

class FakeNotificationRecipient extends FakeModel implements NotificationRecipient
{
    public function displayName(): string
    {
        return 'Test User';
    }

    public function notificationAddresses(): iterable
    {
        return ['test@example.com', '+33000000000'];
    }

    public function preferredLocale(): ?string
    {
        return 'fr-FR';
    }
}
