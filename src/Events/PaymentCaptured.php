<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Payment;

class PaymentCaptured extends Event
{
    public function __construct(public readonly Payment $payment) {}
}
