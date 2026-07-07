<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Contracts;

use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

/**
 * Driver — the per-provider implementation that knows how to talk to
 * Stripe, PayPal, a bank, etc. Each driver is stateless beyond its
 * configuration array, and must always return a normalized
 * {@see PaymentResponse}.
 */
interface Driver
{
    /** Stable identifier, e.g. 'stripe', 'bank_transfer'. */
    public function name(): string;

    public function isConfigured(): bool;

    public function authorize(PaymentRequest $req): PaymentResponse;

    public function capture(PaymentRequest $req): PaymentResponse;

    public function refund(RefundRequest $req): PaymentResponse;

    public function void(PaymentRequest $req): PaymentResponse;

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent;
}
