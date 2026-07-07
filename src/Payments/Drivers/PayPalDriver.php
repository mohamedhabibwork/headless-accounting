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
 * PayPalDriver — Orders v2 API.
 */
final class PayPalDriver extends AbstractHttpDriver
{
    public function __construct(array $config, ?Client $http = null)
    {
        parent::__construct($config, $http ?? new Client(['timeout' => $config['timeout'] ?? 10.0]));
    }

    private function base(): string
    {
        return $this->config['sandbox']
            ? $this->config['sandbox_url']
            : $this->config['live_url'];
    }

    public function name(): string
    {
        return 'paypal';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['client_id']) && ! empty($this->config['secret']);
    }

    private function authHeader(): array
    {
        $token = base64_encode($this->config['client_id'].':'.$this->config['secret']);
        $resp = $this->request('POST', $this->base().'/v1/oauth2/token', [
            'Authorization' => 'Basic '.$token,
        ], ['grant_type' => 'client_credentials']);

        return ['Authorization' => 'Bearer '.$resp['data']['access_token']];
    }

    public function authorize(PaymentRequest $req): PaymentResponse
    {
        $headers = $this->authHeader();
        $resp = $this->request('POST', $this->base().'/v2/checkout/orders', $headers, [
            'intent' => $req->capture ? 'CAPTURE' : 'AUTHORIZE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $req->currency,
                    'value' => number_format($req->amountMinor / (10 ** Currency::decimals($req->currency)), Currency::decimals($req->currency), '.', ''),
                ],
                'custom_id' => (string) $req->payable->getKey(),
                'invoice_id' => method_exists($req->payable, 'number') ? (string) $req->payable->number : null,
            ]],
        ]);
        $links = collect($resp['data']['links'] ?? []);
        $approval = $links->firstWhere('rel', 'approve')['href'] ?? null;

        return PaymentResponse::requiresAction($resp['data']['id'] ?? null, null, $approval, $resp['data']);
    }

    public function capture(PaymentRequest $req): PaymentResponse
    {
        $headers = $this->authHeader();
        $resp = $this->request('POST', $this->base().'/v2/checkout/orders/'.$req->token.'/capture', $headers);

        return PaymentResponse::success($resp['data']['id'] ?? $req->token, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function refund(RefundRequest $req): PaymentResponse
    {
        $headers = $this->authHeader();
        $resp = $this->request('POST', $this->base().'/v2/payments/captures/'.$req->payment->provider_id.'/refund', $headers, [
            'amount' => [
                'value' => number_format($req->amountMinor / (10 ** Currency::decimals($req->currency)), Currency::decimals($req->currency), '.', ''),
                'currency_code' => $req->currency,
            ],
        ]);

        return PaymentResponse::success($resp['data']['id'] ?? null, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function void(PaymentRequest $req): PaymentResponse
    {
        $headers = $this->authHeader();
        $resp = $this->request('POST', $this->base().'/v2/checkout/orders/'.$req->token, $headers);

        return PaymentResponse::success($resp['data']['id'] ?? $req->token, null, null, $resp['data']);
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent
    {
        return new WebhookEvent(
            driver: $this->name(),
            providerEventId: (string) ($payload['id'] ?? uniqid($this->config['event_prefix'] ?? 'paypal_', true)),
            type: (string) ($payload['event_type'] ?? 'unknown'),
            paymentId: $payload['resource']['id'] ?? null,
            amountMinor: isset($payload['resource']['amount']['value'])
                ? (int) round((float) $payload['resource']['amount']['value'] * 100)
                : null,
            currency: isset($payload['resource']['amount']['currency_code']) ? strtoupper($payload['resource']['amount']['currency_code']) : null,
            raw: $payload,
        );
    }
}
