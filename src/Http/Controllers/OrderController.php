<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Discount\ApplyDiscount;
use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CalculateOrderTotals;
use Headless\Accounting\Actions\Payment\RefundPayment;
use Headless\Accounting\Http\Requests\AddItemRequest;
use Headless\Accounting\Http\Requests\ApplyDiscountRequest;
use Headless\Accounting\Http\Resources\OrderResource;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * OrderController — read / mutate endpoints that aren't part of the
 * checkout-onboarding flow but still apply to orders: show, add an extra
 * item, recompute totals, apply a discount, refund.
 */
class OrderController extends Controller
{
    public function show(int $orderId): OrderResource
    {
        $order = Order::query()
            ->with(['items', 'adjustments', 'payments', 'customer', 'invoices'])
            ->withSum(
                ['payments as paid_sum_minor' => fn ($q) => $q->where('state', Payment::STATE_CAPTURED)],
                'amount_minor',
            )
            ->findOrFail($orderId);

        return new OrderResource($order);
    }

    public function addItem(AddItemRequest $request, AddItemToOrder $add, int $orderId, int $variantId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $variant = ProductVariant::findOrFail($variantId);

        $item = $add->execute(
            order: $order,
            variant: $variant,
            quantity: (int) $request->validated('quantity'),
            unitPriceMinorOverride: $request->filled('unit_price_minor')
                ? (int) $request->validated('unit_price_minor')
                : null,
        );

        return new JsonResponse(['item_id' => $item->id], 201);
    }

    public function recalculate(CalculateOrderTotals $calc, int $orderId): OrderResource
    {
        $order = Order::findOrFail($orderId);

        return new OrderResource($calc->execute($order));
    }

    public function applyDiscount(ApplyDiscountRequest $request, ApplyDiscount $apply, int $orderId): OrderResource
    {
        $order = Order::findOrFail($orderId);

        $discountRef = $request->validated('discount_id')
            ?? $request->validated('code')
            ?? $request->validated('coupon');

        $updated = $apply->execute(
            order: $order,
            discount: is_numeric($discountRef) ? (int) $discountRef : (string) $discountRef,
            couponCode: (string) ($request->validated('code') ?? $request->validated('coupon') ?? $discountRef),
        );

        return new OrderResource($updated);
    }

    /**
     * Refund the order against its most recent captured payment.
     *
     * The README promises a `POST /orders/{id}/refunds` endpoint that
     * refunds by order (rather than by payment-id) — that's a common
     * merchant flow ("refund this order"). The underlying action still
     * takes a Payment row, so we resolve it from `payable = order` and
     * delegate.
     */
    public function refund(Request $request, RefundPayment $refund, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $payment = $request->filled('payment_id')
            ? Payment::query()->where('payable_type', $order->getMorphClass())
                ->where('payable_id', $order->id)
                ->findOrFail((int) $request->input('payment_id'))
            : Payment::query()
                ->where('payable_type', $order->getMorphClass())
                ->where('payable_id', $order->id)
                ->where('state', Payment::STATE_CAPTURED)
                ->orderByDesc('id')
                ->firstOrFail();

        $refundRow = $refund->execute(
            payment: $payment,
            amountMinor: $request->filled('amount_minor') ? (int) $request->input('amount_minor') : null,
            reason: $request->input('reason'),
        );

        return new JsonResponse([
            'refund_id' => $refundRow->id,
            'payment_id' => $payment->id,
            'amount_minor' => $refundRow->amount_minor,
        ], 201);
    }
}
