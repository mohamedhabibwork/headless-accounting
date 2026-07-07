<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Order\AddItemToOrder;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Http\Requests\AddItemRequest;
use Headless\Accounting\Http\Resources\OrderResource;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CartController — persists the "Create cart" / "Add item" surface that
 * the README promises in its HTTP API table. The package treats an Order
 * in `STATE_CART` as the cart document, so endpoints here delegate to
 * {@see CreateOrder} and {@see AddItemToOrder}.
 */
class CartController extends Controller
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

        return new JsonResponse([
            'cart_id' => $order->id,
            'order_id' => $order->id,
            'number' => $order->number,
            'state' => $order->state,
            'currency' => $order->currency,
        ], 201);
    }

    public function addItem(AddItemRequest $request, AddItemToOrder $add, int $cartId, int $variantId): JsonResponse
    {
        $order = Order::query()->where('state', Order::STATE_CART)->findOrFail($cartId);
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

    public function show(int $cartId): OrderResource
    {
        $order = Order::query()
            ->where('state', Order::STATE_CART)
            ->with('items')
            ->findOrFail($cartId);

        return new OrderResource($order);
    }

    public function destroy(int $cartId): JsonResponse
    {
        $order = Order::query()->where('state', Order::STATE_CART)->findOrFail($cartId);
        $order->items()->delete();
        $order->delete();

        return new JsonResponse(['ok' => true], 204);
    }
}
