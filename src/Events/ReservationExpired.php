<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\StockReservation;

class ReservationExpired extends Event
{
    public function __construct(public readonly StockReservation $reservation) {}
}
