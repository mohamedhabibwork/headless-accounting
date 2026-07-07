<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Order;

class OrderFulfilled extends Event
{
    public function __construct(public readonly Order $order) {}
}
