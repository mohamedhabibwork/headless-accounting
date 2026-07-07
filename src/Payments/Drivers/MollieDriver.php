<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Drivers;

use GuzzleHttp\Client;
use Headless\Accounting\Currency\Currency;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

final class MollieDriver extends AbstractHttpDriver
{
    public function __construct(array $config, ?Client $http = null)
    {
        parent::__construct($config, $http ?? new Client(['timeout' => $config['timeout'] ?? 10.0]));
    }

    public function name(): string
    {
        return 'mollie';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['api_key']);
    }

    public function authorize(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', ($this->config['api_url'] ?? 'https://api.mollie.com/v2').'/payments', $this->headers(), [
            'amount' => ['currency' => $req->currency, 'value' => number_format($req->amountMinor / (10 ** Currency::decimals($req->currency)), Currency::decimals($req->currency), '.', '')],
            'description' => 'Order '.($req->payable->number ?? $req->payable->getKey()),
            'redirectUrl' => $req->returnUrl,
            'method' => $req->method,        // 'ideal', 'bancontact', 'creditcard', …
            'metadata' => $req->metadata,
        ]);
        $paymentId = $resp['data']['id'] ?? null;
        $checkout = $resp['data']['_links']['checkout']['href'] ?? null;

        return PaymentResponse::requiresAction($paymentId, null, $checkout, $resp['data']);
    }

    public function capture(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('GET', ($this->config['api_url'] ?? 'https://api.mollie.com/v2').'/payments/'.$req->token, $this->headers());

        return PaymentResponse::success($resp['data']['id'] ?? $req->token, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function refund(RefundRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', ($this->config['api_url'] ?? 'https://api.mollie.com/v2').'/payments/'.$req->payment->provider_id.'/refunds', $this->headers(), [
            'amount' => ['currency' => $req->currency, 'value' => number_format($req->amountMinor / (10 ** Currency::decimals($req->currency)), Currency::decimals($req->currency), '.', '')],
        ]);

        return PaymentResponse::success($resp['data']['id'] ?? null, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function void(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('DELETE', ($this->config['api_url'] ?? 'https://api.mollie.com/v2').'/payments/'.$req->token, $this->headers());

        return PaymentResponse::success($resp['data']['id'] ?? $req->token, null, null, $resp['data']);
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent
    {
        return new WebhookEvent(
            driver: $this->name(),
            providerEventId: (string) ($payload['id'] ?? uniqid($this->config['event_prefix'] ?? 'mollie_', true)),
            type: (string) ($payload['type'] ?? 'payment.updated'),
            paymentId: $payload['resource']['id'] ?? null,
            amountMinor: isset($payload['resource']['amount']['value'])
                ? (int) round((float) $payload['resource']['amount']['value'] * 100)
                : null,
            currency: isset($payload['resource']['amount']['currency']) ? strtoupper($payload['resource']['amount']['currency']) : null,
            raw: $payload,
        );
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->config['api_key']];
    }
}
