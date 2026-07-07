<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Drivers;

use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

/**
 * CashOnDeliveryDriver — settlement happens when the courier hands over cash.
 */
final class CashOnDeliveryDriver implements Driver
{
    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return 'cash_on_delivery';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function authorize(PaymentRequest $req): PaymentResponse
    {
        return new PaymentResponse(
            success: true,
            driverState: 'pending_cod',
            providerId: 'cod-'.uniqid(),
            amountMinor: $req->amountMinor,
            currency: $req->currency,
        );
    }

    public function capture(PaymentRequest $req): PaymentResponse
    {
        return new PaymentResponse(
            success: true,
            driverState: 'captured',
            providerId: $req->token,
            amountMinor: $req->amountMinor,
            currency: $req->currency,
        );
    }

    public function refund(RefundRequest $req): PaymentResponse
    {
        return PaymentResponse::success('cod-refund-'.uniqid(), $req->amountMinor, $req->currency, ['instruction' => 'Refund collected on delivery']);
    }

    public function void(PaymentRequest $req): PaymentResponse
    {
        return PaymentResponse::success($req->token, null, null, ['cancelled' => true]);
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent
    {
        return new WebhookEvent(
            driver: $this->name(),
            providerEventId: (string) ($payload['id'] ?? uniqid('cod_', true)),
            type: (string) ($payload['type'] ?? 'unknown'),
            paymentId: $payload['reference'] ?? null,
            amountMinor: isset($payload['amount_minor']) ? (int) $payload['amount_minor'] : null,
            currency: isset($payload['currency']) ? strtoupper($payload['currency']) : null,
            raw: $payload,
        );
    }
}
