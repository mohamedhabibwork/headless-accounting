<?php

declare(strict_types=1);

namespace Headless\Accounting\States;

/**
 * Pure-logic state machine for an ApprovalInstance.
 */
class ApprovalStateMachine
{
    public const STATE_PENDING = 'pending';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    public const STATE_CANCELLED = 'cancelled';

    public static function states(): array
    {
        return [self::STATE_PENDING, self::STATE_APPROVED, self::STATE_REJECTED, self::STATE_CANCELLED];
    }
}
