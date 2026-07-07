<?php

declare(strict_types=1);

use Headless\Accounting\Listeners\PaymentWebhookListener;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\WebhookEvent;
use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\Contracts\Gateway;
use Headless\Accounting\Payments\WebhookEvent as WebhookDTO;

describe('Webhook idempotency', function () {
    it('stores webhook events and is idempotent on duplicate provider_event_id', function () {
        $driver = Mockery::mock(Driver::class);
        $driver->shouldReceive('name')->andReturn('mock');
        $driver->shouldReceive('isConfigured')->andReturn(true);
        $driver->shouldReceive('handleWebhook')->andReturn(
            new WebhookDTO('mock', 'evt_abc_1', 'payment_intent.succeeded', 'pi_999', 5000, 'EUR', ['raw' => 1]),
            new WebhookDTO('mock', 'evt_abc_1', 'payment_intent.succeeded', 'pi_999', 5000, 'EUR', ['raw' => 2]),
        );
        app(Gateway::class)->register('mock', $driver);

        // First delivery: stored as processed.
        $w1 = app(Gateway::class)->driver('mock')->handleWebhook([], null);
        WebhookEvent::create([
            'driver' => $w1->driver,
            'provider_event_id' => $w1->providerEventId,
            'event_type' => $w1->type,
            'payload' => $w1->raw,
            'received_at' => now(),
        ]);
        $first = WebhookEvent::query()->where('provider_event_id', 'evt_abc_1')->first();
        expect($first)->not->toBeNull();

        // Second delivery: still the same id; should not be persisted twice.
        WebhookEvent::create([
            'driver' => $w1->driver,
            'provider_event_id' => 'evt_abc_1',                   // duplicate
            'event_type' => 'DUPLICATE-MARK',
            'payload' => [],
            'received_at' => now(),
        ]);

        // Our test asserts that even after attempting a second insert, the
        // unique constraint should reject it.
        $count = WebhookEvent::query()->where('event_type', 'DUPLICATE-MARK')->count();
        expect($count)->toBe(0);                                 // unique index kicked in
    });
});

describe('PaymentWebhookListener', function () {
    it('flips a payment to captured when payment.captured event lands', function () {
        $order = Order::create([
            'number' => 'ORD-1', 'currency' => 'EUR', 'channel_code' => 'web',
            'state' => 'placed',
        ]);
        $payment = Payment::create([
            'number' => 'PAY-1',
            'payable_type' => 'order',
            'payable_id' => $order->id,
            'currency' => 'EUR',
            'amount_minor' => 5000,
            'driver' => 'mock',
            'state' => Payment::STATE_PENDING,
            'provider_id' => 'pi_999',
        ]);

        $evt = new WebhookDTO('mock', 'evt_1', 'payment.captured', 'pi_999', 5000, 'EUR');
        app(PaymentWebhookListener::class)->handle($evt);

        $payment->refresh();
        expect($payment->state)->toBe(Payment::STATE_CAPTURED);
        expect($payment->captured_at)->not->toBeNull();
    });

    it('flips a payment to failed when payment.failed event lands', function () {
        $payment = Payment::create([
            'number' => 'PAY-2',
            'payable_type' => 'order',
            'payable_id' => 1,
            'currency' => 'EUR',
            'amount_minor' => 1000,
            'driver' => 'mock',
            'state' => Payment::STATE_PENDING,
            'provider_id' => 'pi_failed',
        ]);
        $evt = new WebhookDTO('mock', 'evt_2', 'payment.failed', 'pi_failed', null, 'EUR');
        app(PaymentWebhookListener::class)->handle($evt);

        $payment->refresh();
        expect($payment->state)->toBe(Payment::STATE_FAILED);
    });
});
