<?php

declare(strict_types=1);

namespace Headless\Accounting\Listeners;

use Headless\Accounting\Models\Payment;
use Headless\Accounting\Payments\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * PaymentWebhookListener — converts normalized {@see WebhookEvent}s into
 * Eloquent state changes. The bridge between providers and our domain.
 *
 *   - "payment.captured"            → Payment.state = captured
 *   - "payment.refunded"            → already handled in refund API path
 *   - "payment.failed"              → Payment.state = failed
 *   - "payment.requires_action"     → Payment.state = pending
 */
final class PaymentWebhookListener
{
    public function handle(WebhookEvent $event): void
    {
        if (! $event->paymentId) {
            return;
        }

        $payment = Payment::query()
            ->where('driver', $event->driver)
            ->where('provider_id', $event->paymentId)
            ->first();

        if (! $payment) {
            Log::info("Payment not found for webhook {$event->type} – possibly a duplicate.", (array) $event->raw);

            return;
        }

        match (true) {
            str_contains($event->type, 'payment.captured') => $payment->update(['state' => Payment::STATE_CAPTURED, 'captured_at' => now()]),
            str_contains($event->type, 'payment.failed') => $payment->update(['state' => Payment::STATE_FAILED]),
            str_contains($event->type, 'payment.authorized') => $payment->update(['state' => Payment::STATE_AUTHORIZED]),
            str_contains($event->type, 'payment.voided') => $payment->update(['state' => Payment::STATE_VOIDED, 'voided_at' => now()]),
            default => null,
        };
    }
}
