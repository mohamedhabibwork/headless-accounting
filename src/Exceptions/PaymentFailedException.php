<?php

declare(strict_types=1);

namespace Headless\Accounting\Exceptions;

class PaymentFailedException extends AccountingException
{
    public static function captureFailed(string $reason): self
    {
        return new self("Payment capture failed: {$reason}");
    }
}
