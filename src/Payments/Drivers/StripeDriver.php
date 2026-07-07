<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Drivers;

use GuzzleHttp\Client;
use Headless\Accounting\Exceptions\PaymentFailedException;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

/**
 * StripeDriver — Stripe Payment Intents + Webhooks.
 *
 * Endpoints used:
 *   POST /v1/payment_intents            — authorize (capture=false) or capture
 *   POST /v1/{id}/capture              — capture
 *   POST /v1/{id}/cancel               — void
 *   POST /v1/refunds                   — refund
 *   POST /v1/webhook_endpoints (out)   — webhook
 *
 * The driver is intentionally thin: each API call is in this class so you
 * can audit, replace or extend it.
 */
final class StripeDriver extends AbstractHttpDriver
{
    public function __construct(array $config, ?Client $http = null)
    {
        parent::__construct($config, $http ?? new Client(['timeout' => $config['timeout'] ?? 10.0]));
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['secret_key']);
    }

    public function authorize(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', ($this->config['api_url'] ?? 'https://api.stripe.com/v1').'/payment_intents', $this->headers(), [
            'amount' => $req->amountMinor,
            'currency' => strtolower($req->currency),
            'payment_method' => $req->token,
            'capture_method' => $req->capture ? 'automatic' : 'manual',
            'confirm' => true,
            'metadata' => array_merge((array) $req->metadata, [
                'payable_type' => $req->payable->getMorphClass(),
                'payable_id' => (string) $req->payable->getKey(),
                'method' => (string) $req->method,
            ]),
            'return_url' => $req->returnUrl,
        ]);

        $intent = $resp['data'];
        if (in_array(($intent['status'] ?? ''), ['succeeded', 'processing'], true)) {
            return PaymentResponse::success($intent['id'], $req->amountMinor, $req->currency, $intent);
        }
        if (($intent['status'] ?? '') === 'requires_action') {
            return PaymentResponse::requiresAction($intent['id'], $intent['client_secret'] ?? null, null, $intent);
        }

        return PaymentResponse::failure(
            $intent['error']['code'] ?? 'unknown',
            $intent['error']['message'] ?? 'Stripe error',
            $intent,
        );
    }

    public function capture(PaymentRequest $req): PaymentResponse
    {
        // Stripe Payment Intents: capture directly if intent exists.
        // Otherwise create + confirm with capture_method=automatic.
        if (! $req->token) {
            return PaymentResponse::failure('missing_token', 'Payment intent id missing.');
        }
        $resp = $this->request('POST', ($this->config['api_url'] ?? 'https://api.stripe.com/v1').'/payment_intents/'.$req->token.'/capture', $this->headers());
        $intent = $resp['data'];

        return PaymentResponse::success($intent['id'] ?? $req->token, $req->amountMinor, $req->currency, $intent);
    }

    public function refund(RefundRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', ($this->config['api_url'] ?? 'https://api.stripe.com/v1').'/refunds', $this->headers(), [
            'payment_intent' => $req->payment->provider_id,
            'amount' => $req->amountMinor,
            'reason' => $req->reason,
        ]);

        return PaymentResponse::success($resp['data']['id'] ?? null, $req->amountMinor, $req->currency, $resp['data']);
    }

    public function void(PaymentRequest $req): PaymentResponse
    {
        $resp = $this->request('POST', ($this->config['api_url'] ?? 'https://api.stripe.com/v1').'/payment_intents/'.$req->token.'/cancel', $this->headers());

        return PaymentResponse::success($resp['data']['id'] ?? $req->token, null, null, $resp['data']);
    }

    public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent
    {
        if (! $this->verifyWebhook($payload, (string) $signature)) {
            throw new PaymentFailedException('Invalid Stripe webhook signature.');
        }

        return new WebhookEvent(
            driver: $this->name(),
            providerEventId: (string) ($payload['id'] ?? uniqid($this->config['event_prefix'] ?? 'stripe_', true)),
            type: (string) ($payload['type'] ?? 'unknown'),
            paymentId: $payload['data']['object']['payment_intent'] ?? null,
            amountMinor: isset($payload['data']['object']['amount']) ? (int) $payload['data']['object']['amount'] : null,
            currency: isset($payload['data']['object']['currency']) ? strtoupper($payload['data']['object']['currency']) : null,
            raw: $payload,
        );
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['secret_key'],
            'Stripe-Version' => $this->config['api_version'],
        ];
    }

    private function verifyWebhook(array $payload, string $signature): bool
    {
        if (! isset($this->config['webhook_secret'])) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $signature) as $v) {
            [$k, $val] = array_pad(explode('=', $v, 2), 2, null);
            if ($k) {
                $parts[$k] = $val;
            }
        }
        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }
        $signedPayload = $parts['t'].'.'.json_encode($payload);
        $expected = hash_hmac('sha256', $signedPayload, (string) $this->config['webhook_secret']);

        return hash_equals($expected, (string) $parts['v1']);
    }
}
