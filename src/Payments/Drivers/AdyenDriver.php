<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Drivers;

use GuzzleHttp\Client;
use Headless\Accounting\Exceptions\PaymentFailedException;
use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

/**
 * AdyenDriver — Adyen Checkout + Webhooks.
 *
 * Uses Adyen's HTTP API directly via Guzzle, so the package ships no
 * third-party SDK. The surface is intentionally small; if you need the
 * full PSP API, replace this driver with one that wraps the composer
 * SDK by implementing {@see Driver}.
 */
final class AdyenDriver extends AbstractHttpDriver
{
    public function __construct(array $config, ?Client $http = null)
    {
        parent::__construct($config, $http ?? new Client(['timeout' => $config['timeout'] ?? 10.0]));
    }

    public function name(): string
    {
        return 'adyen';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['api_key']) && ! empty($this->config['merchant_account']);
    }

    public function authorize(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', $this->base().'/payments', $this->headers(), [
            'amount' => ['currency' => $req->currency, 'value' => $req->amountMinor],
            'reference' => (string) $req->payable->getKey(),
            'paymentMethod' => ['type' => $req->method ?? 'scheme', 'storedPaymentMethodId' => $req->token],
            'merchantAccount' => $this->config['merchant_account'],
            'captureDelayHours' => $req->capture ? 0 : null,
            'returnUrl' => $req->returnUrl,
            'metadata' => (object) array_merge((array) $req->metadata, [
                'payable_type' => $req->payable->getMorphClass(),
                'payable_id' => (string) $req->payable->getKey(),
            ]),
        ]);

        return $this->fromAdyenResponse($resp['data'], $req);
    }

    public function capture(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', $this->base().'/payments/'.$req->token.'/captures', $this->headers(), [
            'amount' => ['currency' => $req->currency, 'value' => $req->amountMinor],
            'merchantAccount' => $this->config['merchant_account'],
        ]);

        return PaymentResponse::success($resp['data']['pspReference'] ?? $req->token, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function refund(RefundRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', $this->base().'/payments/'.$req->payment->provider_id.'/refunds', $this->headers(), [
            'amount' => ['currency' => $req->currency, 'value' => $req->amountMinor],
            'merchantAccount' => $this->config['merchant_account'],
            'reference' => $req->payment->number,
        ]);

        return PaymentResponse::success($resp['data']['pspReference'] ?? null, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function void(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', $this->base().'/payments/'.$req->token.'/cancels', $this->headers(), [
            'merchantAccount' => $this->config['merchant_account'],
        ]);

        return PaymentResponse::success($resp['data']['pspReference'] ?? $req->token, null, null, $resp['data']);
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent
    {
        // Adyen uses HMAC-SHA256 over the body with a key-prefixed secret.
        if ($signature && ! $this->verifyAdyenWebhook($payload, (string) $signature)) {
            throw new PaymentFailedException('Invalid Adyen webhook signature.');
        }

        $data = $payload['notificationItems'][0]['NotificationRequestItem'] ?? [];

        return new WebhookEvent(
            driver: $this->name(),
            providerEventId: (string) ($data['eventCode'].'-'.$data['success'] ?? uniqid($this->config['event_prefix'] ?? 'adyen_', true)),
            type: (string) ($data['eventCode'] ?? 'unknown'),
            paymentId: $data['pspReference'] ?? null,
            amountMinor: isset($data['amount']['value']) ? (int) $data['amount']['value'] : null,
            currency: isset($data['amount']['currency']) ? strtoupper($data['amount']['currency']) : null,
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
            'X-API-Key' => $this->config['api_key'],
            'Content-Type' => 'application/json',
        ];
    }

    private function fromAdyenResponse(array $data, PaymentRequest $req): PaymentResponse
    {
        if (($data['resultCode'] ?? '') === 'Authorised' || ($data['resultCode'] ?? '') === 'CaptureReceived') {
            return PaymentResponse::success($data['pspReference'] ?? null, $req->amountMinor, $req->currency, $data);
        }
        if (in_array(($data['resultCode'] ?? ''), ['RedirectShopper', 'AuthenticationFinished'], true)) {
            return PaymentResponse::requiresAction($data['pspReference'] ?? null, null, $data['redirect']['url'] ?? null, $data);
        }

        return PaymentResponse::failure(
            $data['errorCode'] ?? 'unknown',
            $data['message'] ?? 'Adyen error',
            $data,
        );
    }

    private function verifyAdyenWebhook(array $payload, string $signature): bool
    {
        $secret = (string) ($this->config['hmac_key'] ?? '');
        if ($secret === '') {
            return false;
        }
        $body = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($hmac, $signature);
    }
}
