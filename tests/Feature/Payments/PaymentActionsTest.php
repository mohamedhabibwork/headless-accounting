<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Payment\CapturePayment;
use Headless\Accounting\Actions\Payment\RefundPayment;
use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Exceptions\CardDeclinedException;
use Headless\Accounting\Exceptions\PaymentFailedException;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Payments\Contracts\Driver;
use Headless\Accounting\Payments\Contracts\Gateway;
use Headless\Accounting\Payments\PaymentResponse;

beforeEach(function () {
    $this->installChart();
});

function captureWith(Driver $driver, Payable $payable, int $amount): Payment
{
    return (new CapturePayment(app(Gateway::class)))->execute(
        payable: $payable, driver: 'mock', method: 'card', token: 'pm_test', amountMinor: $amount,
    );
}

describe('CapturePayment action', function () {

    it('persists a captured payment and flips the order paid_at', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 5000;
        $order->save();

        $mock = Mockery::mock(Driver::class);
        $mock->shouldReceive('capture')->andReturn(
            PaymentResponse::success('pi_full', 5000, 'EUR'),
        );
        app(Gateway::class)->register('mock', $mock);

        $payment = captureWith($mock, $order, 5000);

        expect($payment->state)->toBe(Payment::STATE_CAPTURED);
        expect($payment->amount_minor)->toBe(5000);
        expect($payment->provider_id)->toBe('pi_full');
        expect($order->fresh()->paid_at)->not->toBeNull();
    });

    it('captures a partial amount without flagging the order paid', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 10000;
        $order->save();

        $mock = Mockery::mock(Driver::class);
        $mock->shouldReceive('capture')->andReturn(
            PaymentResponse::success('pi_partial', 3000, 'EUR'),
        );
        app(Gateway::class)->register('mock', $mock);

        $payment = captureWith($mock, $order, 3000);

        expect($payment->amount_minor)->toBe(3000);
        expect($order->fresh()->paid_at)->toBeNull();
    });

    it('throws CardDeclinedException on a "declined" error code', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 100;
        $order->save();

        $mock = Mockery::mock(Driver::class);
        $mock->shouldReceive('capture')->andReturn(
            PaymentResponse::failure('card_declined', 'Your card was declined.'),
        );
        app(Gateway::class)->register('mock', $mock);

        expect(fn () => captureWith($mock, $order, 100))
            ->toThrow(CardDeclinedException::class);
    });

    it('throws PaymentFailedException on generic failure', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 100;
        $order->save();

        $mock = Mockery::mock(Driver::class);
        $mock->shouldReceive('capture')->andReturn(
            PaymentResponse::failure('unknown', 'oops'),
        );
        app(Gateway::class)->register('mock', $mock);

        expect(fn () => captureWith($mock, $order, 100))
            ->toThrow(PaymentFailedException::class);
    });

    it('refuses to capture a zero or negative amount', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 0;
        $order->save();

        expect(fn () => captureWith(Mockery::mock(Driver::class), $order, 0))
            ->toThrow(PaymentFailedException::class);
    });
});

describe('RefundPayment action', function () {

    it('marks the payment partially_refunded when partial', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 5000;
        $order->save();

        $mock = Mockery::mock(Driver::class);
        $mock->shouldReceive('capture')->andReturn(PaymentResponse::success('pi_x', 5000, 'EUR'));
        $mock->shouldReceive('refund')->andReturn(PaymentResponse::success('re_x', 2000, 'EUR'));
        app(Gateway::class)->register('mock', $mock);

        $payment = captureWith($mock, $order, 5000);
        $refund = (new RefundPayment(app(Gateway::class)))->execute(
            payment: $payment, amountMinor: 2000, reason: 'partial',
        );

        expect($refund->amount_minor)->toBe(2000);
        $payment = $payment->fresh();
        expect($payment->state)->toBe(Payment::STATE_PARTIAL_REFUNDED);
    });

    it('marks the payment fully refunded and flips refunded_at', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 5000;
        $order->save();

        $mock = Mockery::mock(Driver::class);
        $mock->shouldReceive('capture')->andReturn(PaymentResponse::success('pi_y', 5000, 'EUR'));
        $mock->shouldReceive('refund')->andReturn(PaymentResponse::success('re_y', 5000, 'EUR'));
        app(Gateway::class)->register('mock', $mock);

        $payment = captureWith($mock, $order, 5000);
        (new RefundPayment(app(Gateway::class)))->execute(payment: $payment, amountMinor: 5000);

        $payment = $payment->fresh();
        expect($payment->state)->toBe(Payment::STATE_REFUNDED);
        expect($payment->refunded_at)->not->toBeNull();
    });

    it('refuses to refund more than refundable', function () {
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->grand_total_minor = 5000;
        $order->save();

        $mock = Mockery::mock(Driver::class);
        $mock->shouldReceive('capture')->andReturn(PaymentResponse::success('pi_z', 5000, 'EUR'));
        app(Gateway::class)->register('mock', $mock);

        $payment = captureWith($mock, $order, 5000);

        expect(fn () => (new RefundPayment(app(Gateway::class)))->execute(
            payment: $payment, amountMinor: 5001,
        ))->toThrow(InvalidArgumentException::class);
    });
});
