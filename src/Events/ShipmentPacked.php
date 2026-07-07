<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\PackStation;

/**
 * ShipmentPacked — fired when a {@see PackStation} is created.
 */
class ShipmentPacked extends Event
{
    public function __construct(public readonly PackStation $packStation) {}
}
