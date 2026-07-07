<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Payment;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\PaymentFailedException;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\PaymentRefund;
use Headless\Accounting\Payments\Contracts\Gateway;
use Headless\Accounting\Payments\RefundRequest;
use Headless\Accounting\Support\Config;
use InvalidArgumentException;

final class RefundPayment extends Action
{
    public function __construct(private readonly Gateway $gateway) {}

    protected function handle(Payment $payment, ?int $amountMinor = null, ?string $reason = null, array $metadata = []): PaymentRefund
    {
        $amountMinor ??= $payment->amountRefundable();
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Nothing to refund.');
        }
        if ($amountMinor > $payment->amountRefundable()) {
            throw new InvalidArgumentException('Refund amount exceeds refundable balance.');
        }

        $driver = $this->gateway->driver($payment->driver);
        $resp = $driver->refund(new RefundRequest($payment, $amountMinor, $payment->currency, $reason, $metadata));

        if (! $resp->success) {
            throw new PaymentFailedException((string) $resp->errorMessage);
        }

        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_minor' => $amountMinor,
            'currency' => $payment->currency,
            'provider_refund_id' => $resp->providerId,
            'reason' => $reason,
            'provider_response' => $resp->raw,
        ]);

        $payment->state = $payment->amountRefundable() === 0
            ? Payment::STATE_REFUNDED
            : Payment::STATE_PARTIAL_REFUNDED;
        $payment->refunded_at = $payment->state === Payment::STATE_REFUNDED ? now() : $payment->refunded_at;
        $payment->save();

        if ($payment->payable instanceof Order && Config::bool('headless-accounting.accounting.auto_post', true)) {
            app(Journal::class)->post(
                source: $payment,
                currency: $payment->currency,
                description: "Refund issued on payment {$payment->number}",
                postings: [
                    ['account' => '4200', 'debit' => $amountMinor, 'memo' => 'Refunds'],
                    ['account' => '1100', 'credit' => $amountMinor, 'memo' => 'Bank / Clearing'],
                ],
            );
        }

        return $refund;
    }
}
