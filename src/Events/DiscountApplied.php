<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\Order;

class DiscountApplied extends Event
{
    public function __construct(
        public readonly Discount $discount,
        public readonly Order $order,
        public readonly DiscountApplication $application,
    ) {}
}
