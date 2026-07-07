<?php

declare(strict_types=1);

namespace Headless\Accounting\Payments\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Headless\Accounting\Exceptions\PaymentFailedException;
use Headless\Accounting\Exceptions\ProviderUnavailableException;
use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Payments\WebhookEvent;

/**
 * AbstractHttpDriver — shared Guzzle-based helpers for first-party drivers
 * that talk to remote APIs (Stripe, PayPal, Mollie, …). Offline drivers
 * (Cash, Check, Bank) extend a simpler base.
 */
abstract class AbstractHttpDriver implements Driver
{
    public function __construct(
        protected readonly array $config,
        protected readonly Client $http = new Client(['timeout' => 15.0]),
    ) {}

    abstract public function name(): string;

    public function isConfigured(): bool
    {
        return true;
    }

    abstract public function authorize(PaymentRequest $req): PaymentResponse;

    abstract public function capture(PaymentRequest $req): PaymentResponse;

    abstract public function refund(RefundRequest $req): PaymentResponse;

    abstract public function void(PaymentRequest $req): PaymentResponse;

    abstract public function handleWebhook(array $payload, ?string $signature = null): WebhookEvent;

    /** @param array<string,string> $headers */
    protected function request(string $method, string $url, array $headers = [], array $body = []): array
    {
        try {
            $resp = $this->http->request($method, $url, [
                'headers' => $headers,
                'json' => $body,
                'http_errors' => false,
            ]);
        } catch (RequestException $e) {
            throw new ProviderUnavailableException("{$this->name()} unreachable: ".$e->getMessage(), 0, $e);
        }

        $status = $resp->getStatusCode();
        $raw = (string) $resp->getBody();
        $data = json_decode($raw, true) ?? [];

        if ($status >= 500) {
            throw new ProviderUnavailableException("{$this->name()} server error ({$status})");
        }

        if ($status >= 400) {
            $msg = $data['error']['message'] ?? $data['message'] ?? 'Unknown';

            throw new PaymentFailedException($msg);
        }

        return ['status' => $status, 'data' => $data, 'raw' => $data];
    }
}
