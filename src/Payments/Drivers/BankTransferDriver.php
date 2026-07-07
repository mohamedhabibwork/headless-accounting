<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Drivers;

use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

/**
 * BankTransferDriver — offline bank/wire. Returns a "pending" payment
 * with a SEPA-formatted reference, and reconciles when the admin marks
 * the transaction as received.
 */
final class BankTransferDriver implements Driver
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'bank_transfer';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['iban']) && ! empty($this->config['bic']);
    }

    public function authorize(PaymentRequest $req): PaymentResponse
    {
        return new PaymentResponse(
            success: true,
            driverState: 'pending',
            providerId: $this->buildReference($req),
            raw: [
                'iban' => $this->config['iban'],
                'bic' => $this->config['bic'],
                'creditor' => $this->config['creditor_name'] ?? null,
            ],
        );
    }

    public function capture(PaymentRequest $req): PaymentResponse
    {
        // Mark the existing Payment as captured when the operator confirms receipt.
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
        // Refunds are also manual; the driver just records the instruction.
        return PaymentResponse::success('manual-'.uniqid(), $req->amountMinor, $req->currency, [
            'instruction' => 'Issue a bank refund to the customer referencing '.$req->payment->provider_id,
        ]);
    }

    public function void(PaymentRequest $req): PaymentResponse
    {
        return PaymentResponse::success($req->token, null, null, ['cancelled' => true]);
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent
    {
        return new WebhookEvent(
            driver: $this->name(),
            providerEventId: (string) ($payload['id'] ?? uniqid($this->config['event_prefix'] ?? 'wire_', true)),
            type: (string) ($payload['kind'] ?? 'unknown'),
            paymentId: $payload['reference'] ?? null,
            amountMinor: isset($payload['amount_minor']) ? (int) $payload['amount_minor'] : null,
            currency: isset($payload['currency']) ? strtoupper($payload['currency']) : null,
            raw: $payload,
        );
    }

    private function buildReference(PaymentRequest $req): string
    {
        $prefix = (string) ($this->config['reference_prefix'] ?? '');
        $orderId = method_exists($req->payable, 'number') ? (string) $req->payable->number : (string) $req->payable->getKey();
        $base = $prefix.$orderId;

        // SEPA reference: alphanumeric, max 140, mod-97 friendly.
        return substr(preg_replace('/[^A-Za-z0-9]/', '', $base), 0, 140);
    }
}
