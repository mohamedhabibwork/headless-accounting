<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Order;

class OrderPaid extends Event
{
    public function __construct(public readonly Order $order) {}
}
