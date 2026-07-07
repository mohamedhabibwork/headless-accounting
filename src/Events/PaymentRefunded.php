<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\PaymentRefund;

class PaymentRefunded extends Event
{
    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentRefund $refund,
    ) {}
}
