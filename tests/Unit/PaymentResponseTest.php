<?php

declare(strict_types=1);

use Headless\Accounting\Payments\PaymentResponse;
use Headless\Accounting\Payments\WebhookEvent;

describe('PaymentResponse factories', function () {

    it('success carries the provider id, amount and currency', function () {
        $r = PaymentResponse::success('pi_001', 1999, 'EUR', ['a' => 1]);
        expect($r->success)->toBeTrue();
        expect($r->driverState)->toBe('succeeded');
        expect($r->providerId)->toBe('pi_001');
        expect($r->amountMinor)->toBe(1999);
        expect($r->currency)->toBe('EUR');
        expect($r->raw)->toBe(['a' => 1]);
    });

    it('requires_action carries client secret or redirect URL', function () {
        $r = PaymentResponse::requiresAction('pi_002', 'secret_x', 'https://3ds.example');
        expect($r->success)->toBeFalse();
        expect($r->driverState)->toBe('requires_action');
        expect($r->clientSecret)->toBe('secret_x');
        expect($r->redirectUrl)->toBe('https://3ds.example');
    });

    it('failure carries error code and message', function () {
        $r = PaymentResponse::failure('declined', 'Card declined.');
        expect($r->success)->toBeFalse();
        expect($r->errorCode)->toBe('declined');
        expect($r->errorMessage)->toBe('Card declined.');
    });

    it('arbitrary driverState can be passed through', function () {
        $r = new PaymentResponse(false, 'awaiting_capture', null, null, null, 5000, 'USD');
        expect($r->driverState)->toBe('awaiting_capture');
        expect($r->amountMinor)->toBe(5000);
    });
});

describe('WebhookEvent value object', function () {

    it('holds an immutable normalized shape', function () {
        $w = new WebhookEvent(
            driver: 'stripe',
            providerEventId: 'evt_42',
            type: 'payment_intent.succeeded',
            paymentId: 'pi_42',
            amountMinor: 9999,
            currency: 'EUR',
            raw: ['foo' => 'bar'],
        );
        expect($w->driver)->toBe('stripe');
        expect($w->providerEventId)->toBe('evt_42');
        expect($w->type)->toBe('payment_intent.succeeded');
        expect($w->paymentId)->toBe('pi_42');
        expect($w->amountMinor)->toBe(9999);
        expect($w->currency)->toBe('EUR');
        expect($w->raw)->toBe(['foo' => 'bar']);
    });

    it('defaults are nullable', function () {
        $w = new WebhookEvent('stripe', 'evt_0', 'ping');
        expect($w->paymentId)->toBeNull();
        expect($w->amountMinor)->toBeNull();
        expect($w->currency)->toBeNull();
        expect($w->raw)->toBe([]);
    });
});
