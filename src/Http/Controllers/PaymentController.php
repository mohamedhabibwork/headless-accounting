<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Payment\CapturePayment;
use Headless\Accounting\Actions\Payment\RefundPayment;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    public function capture(Request $request, CapturePayment $capture, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $payment = $capture->execute(
            payable: $order,
            driver: $request->input('driver'),
            method: $request->input('method'),
            token: $request->input('token'),
            amountMinor: (int) ($request->input('amount_minor') ?? 0) ?: null,
            returnUrl: $request->input('return_url'),
            metadata: (array) $request->input('metadata', []),
        );

        return new JsonResponse([
            'payment_id' => $payment->id,
            'state' => $payment->state,
            'provider_id' => $payment->provider_id,
        ], 201);
    }

    public function refund(Request $request, RefundPayment $refund, int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);
        $refundRow = $refund->execute(
            payment: $payment,
            amountMinor: (int) ($request->input('amount_minor') ?? 0) ?: null,
            reason: $request->input('reason'),
        );

        return new JsonResponse([
            'refund_id' => $refundRow->id,
            'amount_minor' => $refundRow->amount_minor,
        ], 201);
    }
}
