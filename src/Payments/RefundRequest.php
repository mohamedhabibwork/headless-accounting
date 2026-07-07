<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments;

use Headless\Accounting\Currency\Money;

final class RefundRequest
{
    public function __construct(
        public readonly Payment $payment,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {}

    public function amount(): Money
    {
        return new Money($this->amountMinor, $this->currency);
    }
}
