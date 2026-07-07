<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Order\PlaceOrder;
use Headless\Accounting\Http\Resources\OrderResource;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CheckoutController — keeps the onboarding endpoints that move an order
 * from cart → placed. Per-order mutating operations (add item, recalc,
 * apply discount, refund) live in {@see OrderController}.
 */
class CheckoutController extends Controller
{
    public function store(Request $request, CreateOrder $create): JsonResponse
    {
        $order = $create->execute(
            customer: $request->user('customer'),
            channel: $request->input('channel', Config::string('headless-accounting.channels.default')),
            currency: $request->input('currency', Config::string('headless-accounting.currency.default')),
            locale: $request->header('Accept-Language', Config::string('headless-accounting.locale.default')),
            billingAddress: (array) $request->input('billing_address', []),
            shippingAddress: (array) $request->input('shipping_address', []),
            metadata: ['tax_inclusive' => (bool) $request->boolean('tax_inclusive', false)],
        );

        return new JsonResponse(['order_id' => $order->id, 'number' => $order->number], 201);
    }

    public function place(PlaceOrder $place, int $orderId): OrderResource
    {
        $order = Order::findOrFail($orderId);

        return new OrderResource($place->execute($order));
    }
}
