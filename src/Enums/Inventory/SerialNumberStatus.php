<?php

declare(strict_types=1);

namespace Headless\Accounting\Enums\Inventory;

use Headless\Accounting\Models\SerialNumber;

/**
 * Lifecycle status for a {@see SerialNumber}.
 *
 * Stored as a snake_case string in the database; the model casts the
 * `status` column to this enum, so call-sites can either compare against
 * the enum case (`SerialNumberStatus::InStock`) or use `->value` to read
 * the raw string.
 */
enum SerialNumberStatus: string
{
    case InStock = 'in_stock';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case InTransit = 'in_transit';
    case Returned = 'returned';
    case UnderRepair = 'under_repair';
    case Retired = 'retired';
    case Lost = 'lost';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
