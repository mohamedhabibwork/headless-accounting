<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\PickList;
use Headless\Accounting\Models\PickListLine;

/**
 * StockPicked — fired every time a {@see PickListLine} transitions to
 * picked or short.
 */
class StockPicked extends Event
{
    public function __construct(
        public readonly PickList $pickList,
        public readonly PickListLine $line,
    ) {}
}
