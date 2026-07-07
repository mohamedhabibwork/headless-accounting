<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments;

final class WebhookEvent
{
    public function __construct(
        public readonly string $driver,
        public readonly string $providerEventId,
        public readonly string $type,                 // 'payment.captured', 'refund.created', …
        public readonly ?string $paymentId = null,     // provider payment id
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly array $raw = [],
    ) {}
}
