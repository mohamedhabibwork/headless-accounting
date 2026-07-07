<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments;

/**
 * PaymentResponse — provider-agnostic response shape. Drivers translate
 * their own quirky payloads into this DTO so callers don't have to deal
 * with the differences.
 */
final class PaymentResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $driverState,                  // 'succeeded', 'requires_action', 'failed', …
        public readonly ?string $providerId = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {}

    public static function success(string $providerId, ?int $amountMinor = null, ?string $currency = null, array $raw = []): self
    {
        return new self(success: true, driverState: 'succeeded', providerId: $providerId, amountMinor: $amountMinor, currency: $currency, raw: $raw);
    }

    public static function requiresAction(string $providerId, ?string $clientSecret = null, ?string $redirectUrl = null, array $raw = []): self
    {
        return new self(success: false, driverState: 'requires_action', providerId: $providerId, clientSecret: $clientSecret, redirectUrl: $redirectUrl, raw: $raw);
    }

    public static function failure(string $code, string $message, array $raw = []): self
    {
        return new self(success: false, driverState: 'failed', errorCode: $code, errorMessage: $message, raw: $raw);
    }
}
