<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Order;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * PlaceOrder — transitions an order from cart/draft → placed. Posts the
 * corresponding journal entry to AR / Sales.
 */
final class PlaceOrder extends Action
{
    public function __construct(private readonly Journal $journal) {}

    protected function handle(Order $order, ?Model $actor = null): Order
    {
        $sm = $order->stateMachine();
        if ($order->state === Order::STATE_CART) {
            $sm->recordTransition(Order::STATE_DRAFT, $actor, 'moved to draft');
        }
        $sm->assertCan(Order::STATE_PLACED);
        $sm->recordTransition(Order::STATE_PLACED, $actor, 'placed');

        $order->placed_at = now();
        $order->save();

        if (Config::bool('headless-accounting.accounting.auto_post', true)) {
            $this->journal->post(
                source: $order,
                currency: $order->currency,
                description: "Order {$order->number} placed",
                postings: [
                    ['account' => '1200', 'debit' => (int) $order->grand_total_minor, 'memo' => 'AR'],
                    ['account' => '2300', 'credit' => (int) $order->grand_total_minor, 'memo' => 'Customer prepayment / AR pending'],
                ],
            );
        }

        return $order;
    }
}
