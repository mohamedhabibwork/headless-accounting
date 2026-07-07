<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Listeners\PaymentWebhookListener;
use Headless\Accounting\Models\WebhookEvent;
use Headless\Accounting\Payments\Contracts\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private readonly Gateway $gateway) {}

    public function handle(Request $request, string $driver): JsonResponse
    {
        try {
            $webhook = $this->gateway->driver($driver)->handleWebhook(
                $request->all(),
                $request->header('Stripe-Signature') ?? $request->header('X-Signature') ?? null,
            );
        } catch (\Throwable $e) {
            Log::warning("Webhook from {$driver} failed: ".$e->getMessage());

            return new JsonResponse(['ok' => false, 'error' => 'unprocessable'], 422);
        }

        // Idempotency: bail if we already processed this event.
        $existing = WebhookEvent::query()
            ->where('driver', $driver)
            ->where('provider_event_id', $webhook->providerEventId)
            ->first();

        if ($existing && $existing->processed_at) {
            return new JsonResponse(['ok' => true, 'duplicate' => true]);
        }

        $row = WebhookEvent::create([
            'driver' => $driver,
            'provider_event_id' => $webhook->providerEventId,
            'event_type' => $webhook->type,
            'payload' => $webhook->raw,
            'outcome' => 'pending',
        ]);

        try {
            app(PaymentWebhookListener::class)->handle($webhook);
            $row->update(['processed_at' => now(), 'outcome' => 'ok']);
        } catch (\Throwable $e) {
            $row->update(['outcome' => 'failed']);

            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        return new JsonResponse(['ok' => true]);
    }
}
