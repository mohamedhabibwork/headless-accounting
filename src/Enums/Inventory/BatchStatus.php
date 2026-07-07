<?php

declare(strict_types=1);

namespace Headless\Accounting\Enums\Inventory;

use Headless\Accounting\Models\Batch;

/**
 * Lifecycle status for a {@see Batch} lot record.
 *
 * Stored as a snake_case string in the database; the model casts the
 * `status` column to this enum, so call-sites can either compare against
 * the enum case (`BatchStatus::Active`) or use `->value` to read the
 * raw string.
 */
enum BatchStatus: string
{
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Recalled = 'recalled';
    case Depleted = 'depleted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
