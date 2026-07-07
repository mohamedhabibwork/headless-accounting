<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Shipment;

/**
 * ShipmentShipped — fired after a {@see Shipment} is dispatched.
 */
class ShipmentShipped extends Event
{
    public function __construct(public readonly Shipment $shipment) {}
}
