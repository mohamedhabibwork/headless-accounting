<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Headless\Accounting\Exceptions\PaymentFailedException;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Payments\Drivers\AdyenDriver;
use Headless\Accounting\Payments\Drivers\BankTransferDriver;
use Headless\Accounting\Payments\Drivers\BraintreeDriver;
use Headless\Accounting\Payments\Drivers\CashOnDeliveryDriver;
use Headless\Accounting\Payments\Drivers\CheckDriver;
use Headless\Accounting\Payments\Drivers\MollieDriver;
use Headless\Accounting\Payments\Drivers\PayPalDriver;
use Headless\Accounting\Payments\Drivers\StripeDriver;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\RefundRequest;

function order_payable(string $currency = 'EUR', int $amount = 5000, string $number = 'ORD-TEST-1'): Order
{
    $o = Order::create([
        'number' => $number,
        'currency' => $currency,
        'channel_code' => 'web',
        'state' => Order::STATE_PLACED,
        'grand_total_minor' => $amount,
        'total_paid_minor' => 0,
    ]);

    return $o;
}

/**
 * Use a Guzzle mock handler so each driver test runs locally without hitting
 * the internet.
 */
function guzzleWith(Response ...$responses): HandlerStack
{
    $mock = new MockHandler($responses);

    return HandlerStack::create($mock);
}

describe('StripeDriver', function () {
    it('reports isConfigured true when secret_key present', function () {
        expect((new StripeDriver(['secret_key' => 'sk_xxx']))->isConfigured())->toBeTrue();
        expect((new StripeDriver([]))->isConfigured())->toBeFalse();
    });

    it('authorize returns requires_action when provider demands 3DS', function () {
        $stack = guzzleWith(new Response(200, [], json_encode([
            'id' => 'pi_123', 'status' => 'requires_action', 'client_secret' => 'pi_123_secret',
        ])));

        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new StripeDriver(['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_x'], $http);

        $resp = $driver->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'stripe', 'card', 'pm_test'));
        expect($resp->success)->toBeFalse();
        expect($resp->driverState)->toBe('requires_action');
        expect($resp->clientSecret)->toBe('pi_123_secret');
    });

    it('authorize returns success when payment lands', function () {
        $stack = guzzleWith(new Response(200, [], json_encode([
            'id' => 'pi_999', 'status' => 'succeeded',
        ])));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new StripeDriver(['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_x'], $http);

        $resp = $driver->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'stripe', 'card', 'pm_test'));
        expect($resp->success)->toBeTrue();
        expect($resp->providerId)->toBe('pi_999');
    });

    it('captures a previously authorized intent', function () {
        $stack = guzzleWith(new Response(200, [], json_encode(['id' => 'pi_a', 'status' => 'succeeded'])));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new StripeDriver(['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_x'], $http);

        $resp = $driver->capture(new PaymentRequest(order_payable(), 5000, 'EUR', 'stripe', null, 'pi_a'));
        expect($resp->providerId)->toBe('pi_a');
    });

    it('refund resolves against Stripe', function () {
        $stack = guzzleWith(new Response(200, [], json_encode(['id' => 're_a'])));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new StripeDriver(['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_x'], $http);

        $payment = Payment::create([
            'number' => 'PAY-1', 'payable_type' => 'order', 'payable_id' => 1,
            'currency' => 'EUR', 'amount_minor' => 5000, 'driver' => 'stripe',
            'method' => 'card', 'state' => 'captured', 'provider_id' => 'pi_a',
        ]);

        $resp = $driver->refund(new RefundRequest($payment, 5000, 'EUR'));
        expect($resp->success)->toBeTrue();
        expect($resp->providerId)->toBe('re_a');
    });

    it('webhook signature verification rejects an invalid signature', function () {
        $driver = new StripeDriver(['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_x']);
        expect(fn () => $driver->handleWebhook(['id' => 'evt_x', 'type' => '...'], 'invalid-signature'))
            ->toThrow(PaymentFailedException::class);
    });

    it('parses a correctly-signed webhook into a normalized event', function () {
        $secret = 'whsec_x';
        $payload = ['id' => 'evt_x', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['payment_intent' => 'pi_x', 'amount' => 1234, 'currency' => 'eur']]];
        $ts = (string) time();
        $signed = $ts.'.'.json_encode($payload);
        $sig = hash_hmac('sha256', $signed, $secret);
        $signature = "t={$ts},v1={$sig}";

        $driver = new StripeDriver(['secret_key' => 'sk_test', 'webhook_secret' => $secret]);
        $w = $driver->handleWebhook($payload, $signature);
        expect($w->driver)->toBe('stripe');
        expect($w->paymentId)->toBe('pi_x');
        expect($w->amountMinor)->toBe(1234);
        expect($w->currency)->toBe('EUR');
    });
});

describe('PayPalDriver', function () {
    it('reports isConfigured true when both id and secret are present', function () {
        expect((new PayPalDriver(['client_id' => 'cli', 'secret' => 'sec']))->isConfigured())->toBeTrue();
        expect((new PayPalDriver([]))->isConfigured())->toBeFalse();
    });

    it('authorize returns a requires_action response with approval URL', function () {
        $tokenResp = new Response(200, [], json_encode(['access_token' => 'TOK']));
        $orderResp = new Response(200, [], json_encode([
            'id' => 'PAY-X',
            'links' => [['rel' => 'approve', 'href' => 'https://approve.example']],
        ]));
        $stack = guzzleWith($tokenResp, $orderResp);
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new PayPalDriver(['client_id' => 'cli', 'secret' => 'sec'], $http);

        $resp = $driver->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'paypal'));
        expect($resp->driverState)->toBe('requires_action');
        expect($resp->redirectUrl)->toBe('https://approve.example');
    });
});

describe('MollieDriver', function () {
    it('authorize returns requires_action pointing to checkout', function () {
        $stack = guzzleWith(new Response(200, [], json_encode([
            'id' => 'tr_abc', '_links' => ['checkout' => ['href' => 'https://mollie/checkout']],
        ])));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new MollieDriver(['api_key' => 'test_x'], $http);

        $resp = $driver->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'mollie'));
        expect($resp->redirectUrl)->toBe('https://mollie/checkout');
        expect($resp->providerId)->toBe('tr_abc');
    });
});

describe('BraintreeDriver', function () {
    it('authorize returns success on a sale', function () {
        $stack = guzzleWith(new Response(200, [], json_encode(['id' => 'tx_1'])));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new BraintreeDriver(['merchant_id' => 'm', 'public_key' => 'p', 'private_key' => 'k'], $http);
        $resp = $driver->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'braintree', null, 'nonce'));
        expect($resp->success)->toBeTrue();
        expect($resp->providerId)->toBe('tx_1');
    });
});

describe('AdyenDriver', function () {
    it('authorize returns success on Authorised resultCode', function () {
        $stack = guzzleWith(new Response(200, [], json_encode([
            'resultCode' => 'Authorised', 'pspReference' => 'psp_1',
        ])));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new AdyenDriver(['api_key' => 'k', 'merchant_account' => 'm'], $http);

        $resp = $driver->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'adyen'));
        expect($resp->success)->toBeTrue();
        expect($resp->providerId)->toBe('psp_1');
    });

    it('authorize returns requires_action when resultCode = RedirectShopper', function () {
        $stack = guzzleWith(new Response(200, [], json_encode([
            'resultCode' => 'RedirectShopper', 'pspReference' => 'psp_2',
            'redirect' => ['url' => 'https://3ds.example'],
        ])));
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new AdyenDriver(['api_key' => 'k', 'merchant_account' => 'm'], $http);

        $resp = $driver->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'adyen'));
        expect($resp->driverState)->toBe('requires_action');
        expect($resp->redirectUrl)->toBe('https://3ds.example');
    });

    it('parses a notification webhook into a normalized event', function () {
        $driver = new AdyenDriver(['api_key' => 'k', 'merchant_account' => 'm']);
        $payload = [
            'live' => 'false',
            'notificationItems' => [
                ['NotificationRequestItem' => [
                    'eventCode' => 'AUTHORISATION',
                    'success' => 'true',
                    'pspReference' => 'psp_42',
                    'amount' => ['currency' => 'EUR', 'value' => 5000],
                ]],
            ],
        ];
        $w = $driver->handleWebhook($payload);
        expect($w->driver)->toBe('adyen');
        expect($w->paymentId)->toBe('psp_42');
        expect($w->amountMinor)->toBe(5000);
        expect($w->currency)->toBe('EUR');
    });
});

describe('BankTransferDriver', function () {
    it('is unconfigured without IBAN+BIC', function () {
        expect((new BankTransferDriver([]))->isConfigured())->toBeFalse();
        expect((new BankTransferDriver(['iban' => 'X', 'bic' => 'Y']))->isConfigured())->toBeTrue();
    });

    it('authorize returns a pending driver-state with a reference number', function () {
        $resp = (new BankTransferDriver(['iban' => 'X', 'bic' => 'Y', 'reference_prefix' => 'ORD-']))
            ->authorize(new PaymentRequest(order_payable(number: 'ORD-2026-0042'), 5000, 'EUR', 'bank_transfer'));

        expect($resp->success)->toBeTrue();
        expect($resp->driverState)->toBe('pending');
        expect($resp->providerId)->toContain('ORD20260042');
        expect($resp->raw['iban'])->toBe('X');
    });

    it('refund returns a manual instruction', function () {
        $payment = Payment::create([
            'number' => 'PAY-1', 'payable_type' => 'order', 'payable_id' => 1,
            'currency' => 'EUR', 'amount_minor' => 5000, 'driver' => 'bank_transfer',
            'method' => null, 'state' => 'captured',
        ]);
        $resp = (new BankTransferDriver(['iban' => 'X', 'bic' => 'Y']))
            ->refund(new RefundRequest($payment, 5000, 'EUR', 'test'));
        expect($resp->success)->toBeTrue();
        expect($resp->raw['instruction'])->toContain('bank refund');
    });
});

describe('CashOnDeliveryDriver', function () {
    it('authorize returns pending_cod', function () {
        $resp = (new CashOnDeliveryDriver([]))->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'cod'));
        expect($resp->driverState)->toBe('pending_cod');
        expect($resp->success)->toBeTrue();
    });
});

describe('CheckDriver', function () {
    it('authorize returns pending_check', function () {
        $resp = (new CheckDriver([]))->authorize(new PaymentRequest(order_payable(), 5000, 'EUR', 'check'));
        expect($resp->driverState)->toBe('pending_check');
        expect($resp->success)->toBeTrue();
    });
});
