<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Order;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Order;
use Illuminate\Database\Eloquent\Model;

final class CancelOrder extends Action
{
    protected function handle(Order $order, ?string $reason = null, ?Model $actor = null): Order
    {
        $order->stateMachine()->assertCan(Order::STATE_CANCELLED);
        $order->stateMachine()->recordTransition(Order::STATE_CANCELLED, $actor, $reason ?? 'cancelled');
        $order->cancelled_at = now();
        $order->save();

        return $order;
    }
}
