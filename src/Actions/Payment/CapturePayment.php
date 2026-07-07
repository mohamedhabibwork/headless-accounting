<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Payment;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Exceptions\CardDeclinedException;
use Headless\Accounting\Exceptions\PaymentFailedException;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Payments\Contracts\Gateway;
use Headless\Accounting\Payments\PaymentRequest;
use Headless\Accounting\Payments\PaymentResponse;
use Illuminate\Support\Str;

/**
 * CapturePayment — captures a payment against a Payable, with a clean
 * normal path plus a couple of error branches.
 *
 *   $payment = (new CapturePayment)->execute(
 *       payable: $order,    // any Payable
 *       driver:  'stripe',
 *       method:  'card',
 *       token:   $request->paymentMethodId,
 *       amountMinor: $request->amount * 100,    // optional: defaults to balance
 *   );
 */
final class CapturePayment extends Action
{
    public function __construct(private readonly Gateway $gateway) {}

    protected function handle(
        Payable $payable,
        string $driver,
        ?string $method = null,
        ?string $token = null,
        ?int $amountMinor = null,
        ?string $returnUrl = null,
        array $metadata = [],
    ): Payment {
        $amountMinor ??= $payable->balanceDue();
        if ($amountMinor <= 0) {
            throw new PaymentFailedException('Nothing to capture.');
        }

        $driverInstance = $this->gateway->driver($driver);
        $req = new PaymentRequest(
            payable: $payable,
            amountMinor: $amountMinor,
            currency: $payable->currency(),
            driver: $driver,
            method: $method,
            token: $token,
            returnUrl: $returnUrl,
            metadata: $metadata,
        );

        $resp = $driverInstance->capture($req);

        return $this->persist($payable, $req, $resp);
    }

    public function persist(Payable $payable, PaymentRequest $req, PaymentResponse $resp): Payment
    {
        if (! $resp->success && $resp->driverState !== 'requires_action') {
            $code = strtolower((string) $resp->errorCode);
            if (str_contains($code, 'declined')) {
                throw new CardDeclinedException((string) $resp->errorMessage);
            }

            throw new PaymentFailedException((string) $resp->errorMessage ?? 'capture failed');
        }

        $payment = Payment::create([
            'number' => 'PAY-'.date('Y').'-'.Str::upper(Str::random(6)),
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'currency' => $req->currency,
            'amount_minor' => $req->amountMinor,
            'driver' => $req->driver,
            'method' => $req->method,
            'state' => $resp->success ? Payment::STATE_CAPTURED : Payment::STATE_PENDING,
            'provider_id' => $resp->providerId,
            'provider_response' => $resp->raw,
            'captured_at' => $resp->success ? now() : null,
            'customer_id' => $payable instanceof Order ? $payable->customer_id : null,
        ]);

        if ($payable instanceof Order && $resp->success && $payment->state === Payment::STATE_CAPTURED) {
            if ($payable->balanceDue() <= 0) {
                $payable->paid_at = $payable->paid_at ?? now();
                $payable->save();
            }
        }

        return $payment;
    }
}
