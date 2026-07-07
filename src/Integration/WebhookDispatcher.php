<?php

declare(strict_types=1);

namespace Headless\Accounting\Integration;

use GuzzleHttp\Client;
use Headless\Accounting\Models\Webhook;
use Headless\Accounting\Models\WebhookDelivery;

/**
 * WebhookDispatcher — small fan-out helper: takes a domain event and
 * posts to all registered Webhooks that subscribe to its type.
 *
 *   WebhookDispatcher::dispatch('order.created', ['order' => $order]);
 */
class WebhookDispatcher
{
    public function __construct(private readonly Client $http = new Client(['timeout' => 10.0])) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function dispatch(string $eventType, array $payload): int
    {
        $hooks = Webhook::query()->where('active', true)->get()
            ->filter(fn (Webhook $h) => in_array($eventType, (array) $h->event_types, true) || in_array('*', (array) $h->event_types, true));

        $count = 0;
        foreach ($hooks as $hook) {
            $this->deliver($hook, $eventType, $payload);
            $count++;
        }

        return $count;
    }

    private function deliver(Webhook $hook, string $eventType, array $payload): WebhookDelivery
    {
        $body = array_merge($payload, [
            'event' => $eventType,
            'delivered_at' => now()->toIso8601String(),
            'webhook_id' => $hook->id,
        ]);

        $signature = hash_hmac('sha256', json_encode($body), $hook->secret);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $hook->id,
            'event_type' => $eventType,
            'payload' => $body,
            'attempt' => 1,
        ]);

        try {
            $resp = $this->http->post($hook->url, [
                'headers' => [
                    'X-Webhook-Event' => $eventType,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                    'Content-Type' => $hook->content_type ?: 'application/json',
                ],
                'json' => $body,
                'http_errors' => false,
            ]);
            $delivery->update([
                'http_status' => $resp->getStatusCode(),
                'delivered_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $delivery->update(['error' => $e->getMessage()]);
        }

        return $delivery;
    }
}
