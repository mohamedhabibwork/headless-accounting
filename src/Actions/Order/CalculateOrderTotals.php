<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Order;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Discounts\DiscountEngine;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderAdjustment;
use Headless\Accounting\Tax\TaxEngine;

/**
 * CalculateOrderTotals — walks the order and computes subtotal,
 * discounts (using the engine), taxes, shipping, and grand total,
 * then persists the resulting adjustments.
 *
 *   subtotal  = Σ line subtotal
 *   - discounts (engine output)
 *   + shipping (from $order->shipping_minor or rules)
 *   + tax (TaxEngine on each line)
 *   = grand_total
 */
final class CalculateOrderTotals extends Action
{
    public function __construct(
        private readonly DiscountEngine $discounts,
        private readonly TaxEngine $taxes,
    ) {}

    protected function handle(Order $order): Order
    {
        $currency = $order->currency;

        $subtotal = 0;
        $taxTotal = 0;
        foreach ($order->items()->cursor() as $item) {
            $subtotal += (int) $item->unit_price_minor * (int) $item->quantity;
        }

        // Discount evaluation
        $items = iterator_to_array($order->items()->cursor());
        $ctx = new EvaluationContext($order, $items, $order->customer, $order->channel_code);

        $discounts = Discount::query()
            ->where('active', true)
            ->where(function ($q) use ($order) {
                $q->whereNull('channel_code')->orWhere('channel_code', $order->channel_code);
            })
            ->orderBy('priority')
            ->get();

        $apps = $this->discounts->run($discounts, $ctx);

        $discountTotal = 0;
        foreach ($apps as $app) {
            $discountTotal += $app->total->amount;
            // Persist per-line adjustments
            foreach ($app->lines() as $line) {
                OrderAdjustment::create([
                    'order_id' => $order->id,
                    'order_item_id' => $this->resolveOrderItemId($order, $line['variant_id'] ?? null),
                    'discount_id' => $app->discountId,
                    'type' => 'discount',
                    'name' => $app->discountName,
                    'amount_minor' => -1 * (int) $line['amount']->amount,
                    'currency' => $currency,
                ]);
            }
        }

        // Tax calculation (per line, using line-subtotal-after-discount)
        $afterDiscountPerLine = $this->distribute($subtotal - $discountTotal, count($items));
        foreach ($items as $i => $item) {
            $lineSubtotal = $afterDiscountPerLine[$i] ?? 0;
            $lineShipAddr = null;
            if ($order->shipping_address_snapshot) {
                $lineShipAddr = (object) (array) $order->shipping_address_snapshot;
            }
            $break = $this->taxes->resolve($item->variant, $lineSubtotal, $currency, $lineShipAddr, null, $item->variant->taxContext());

            $taxTotal += $break->total()->amount;
            OrderAdjustment::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'type' => 'tax',
                'name' => 'Tax',
                'amount_minor' => $break->total()->amount,
                'currency' => $currency,
            ]);
        }

        $order->subtotal_minor = $subtotal;
        $order->discount_total_minor = $discountTotal;
        $order->tax_total_minor = $taxTotal;
        $order->grand_total_minor = max(0, $subtotal - $discountTotal + $taxTotal + (int) $order->shipping_minor);
        $order->item_count = (int) $order->items()->sum('quantity');
        $order->save();
        $order->recordEvent('order.totals_recalculated', [
            'subtotal' => $subtotal,
            'discount' => $discountTotal,
            'tax' => $taxTotal,
            'shipping' => (int) $order->shipping_minor,
            'grand' => (int) $order->grand_total_minor,
        ]);

        return $order->fresh(['items', 'adjustments']);
    }

    /** Returns an array of [$subtotalPerLine, …] for each OrderItem. */
    private function distribute(int $total, int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $base = intdiv($total, $n);
        $rem = $total - ($base * $n);
        $out = array_fill(0, $n, $base);
        for ($i = 0; $i < abs($rem); $i++) {
            $out[$i] += ($rem > 0 ? 1 : -1);
        }

        return $out;
    }

    private function resolveOrderItemId(Order $order, ?int $variantId): ?int
    {
        if (! $variantId) {
            return null;
        }

        return $order->items()->where('variant_id', $variantId)->value('id');
    }
}
