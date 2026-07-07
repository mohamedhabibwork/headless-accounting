<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Order;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

final class MarkOrderPaid extends Action
{
    public function __construct(private readonly Journal $journal) {}

    protected function handle(Order $order, ?Model $actor = null): Order
    {
        $order->stateMachine()->assertCan(Order::STATE_PAID);
        $order->stateMachine()->recordTransition(Order::STATE_PAID, $actor, 'fully paid');
        $order->paid_at = now();
        $order->save();

        if (Config::bool('headless-accounting.accounting.auto_post', true)) {
            $this->journal->post(
                source: $order,
                currency: $order->currency,
                description: "Order {$order->number} paid in full",
                postings: [
                    ['account' => '1100', 'debit' => (int) $order->grand_total_minor, 'memo' => 'Bank'],
                    ['account' => '1200', 'credit' => (int) $order->grand_total_minor, 'memo' => 'AR cleared'],
                ],
            );
        }

        return $order;
    }
}
