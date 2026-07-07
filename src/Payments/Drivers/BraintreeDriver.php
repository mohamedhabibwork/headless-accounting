<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Drivers;

use GuzzleHttp\Client;
use Headless\Accounting\Currency\Currency;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

/**
 * BraintreeDriver — uses Braintree's HTTP API via Guzzle. For most users
 * you'll swap this for the composer SDK; the surface remains the same.
 */
final class BraintreeDriver extends AbstractHttpDriver
{
    public function __construct(array $config, ?Client $http = null)
    {
        parent::__construct($config, $http ?? new Client(['timeout' => $config['timeout'] ?? 10.0]));
    }

    public function name(): string
    {
        return 'braintree';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['merchant_id']) && ! empty($this->config['public_key']) && ! empty($this->config['private_key']);
    }

    public function authorize(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', $this->base().'/transactions', $this->headers(), [
            'type' => $req->capture ? 'sale' : 'authorize',
            'amount' => number_format($req->amountMinor / (10 ** Currency::decimals($req->currency)), Currency::decimals($req->currency), '.', ''),
            'currencyIsoCode' => $req->currency,
            'paymentMethodNonce' => $req->token,
            'orderId' => (string) $req->payable->getKey(),
            'options' => ['submitForSettlement' => $req->capture],
        ]);

        $t = $resp['data'] ?? [];

        return PaymentResponse::success($t['id'] ?? null, $req->amountMinor, $req->currency, $t);
    }

    public function capture(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', $this->base().'/transactions/'.$req->token.'/submit_for_settlement', $this->headers());

        return PaymentResponse::success($resp['data']['id'] ?? $req->token, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function refund(RefundRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', $this->base().'/transactions/'.$req->payment->provider_id.'/refund', $this->headers(), [
            'amount' => number_format($req->amountMinor / (10 ** Currency::decimals($req->currency)), Currency::decimals($req->currency), '.', ''),
        ]);

        return PaymentResponse::success($resp['data']['id'] ?? null, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function void(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('PUT', $this->base().'/transactions/'.$req->token, $this->headers(), [
            'status' => 'voided',
        ]);

        return PaymentResponse::success($resp['data']['id'] ?? $req->token, null, null, $resp['data']);
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent
    {
        return new WebhookEvent(
            driver: $this->name(),
            providerEventId: (string) ($payload['id'] ?? uniqid($this->config['event_prefix'] ?? 'bt_', true)),
            type: (string) ($payload['kind'] ?? 'unknown'),
            paymentId: $payload['resource']['id'] ?? null,
            amountMinor: isset($payload['resource']['amount'])
                ? (int) round((float) $payload['resource']['amount'] * 100)
                : null,
            currency: isset($payload['resource']['currencyIsoCode']) ? strtoupper($payload['resource']['currencyIsoCode']) : null,
            raw: $payload,
        );
    }

    private function base(): string
    {
        return $this->config['sandbox']
            ? $this->config['sandbox_url']
            : $this->config['live_url'];
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode("{$this->config['public_key']}:{$this->config['private_key']}"),
            'Content-Type' => 'application/json',
        ];
    }
}
