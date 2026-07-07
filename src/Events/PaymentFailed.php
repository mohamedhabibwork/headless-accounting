<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Payment;
use Headless\Accounting\Payments\PaymentResponse;

class PaymentFailed extends Event
{
    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentResponse $response,
    ) {}
}
