<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Currency\Money;

final class PaymentRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly Payable $payable,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $driver,            // 'stripe', etc.
        public readonly ?string $method = null,     // 'card', 'sepa', …
        public readonly ?string $token = null,      // payment method id
        public readonly ?string $returnUrl = null,
        public readonly array $metadata = [],
        public readonly ?string $customerId = null,
        public readonly bool $capture = true,    // auth-only when false
    ) {}

    public function amount(): Money
    {
        return new Money($this->amountMinor, $this->currency);
    }
}
