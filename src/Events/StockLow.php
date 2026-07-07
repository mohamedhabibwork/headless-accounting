<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\StockItem;

class StockLow extends Event
{
    public function __construct(
        public readonly StockItem $stockItem,
        public readonly int $threshold,
    ) {}
}
