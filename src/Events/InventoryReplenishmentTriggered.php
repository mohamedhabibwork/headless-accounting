<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Warehouse;

class InventoryReplenishmentTriggered extends Event
{
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly Warehouse $warehouse,
        public readonly int $currentOnHand,
        public readonly int $reorderPoint,
        public readonly int $suggestedQuantity,
    ) {}
}
