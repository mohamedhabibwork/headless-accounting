<?php

declare(strict_types=1);

namespace Headless\Accounting\States;

use Headless\Accounting\Exceptions\InvalidTransitionException;
use Headless\Accounting\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * OrderStateMachine — pure-logic state machine for the Order aggregate.
 *
 * It does *not* modify the order directly; instead it returns intent
 * (the Action layer performs the actual save + side effects). This keeps
 * the state graph testable in isolation.
 */
final class OrderStateMachine
{
    /**
     * Allowed transitions: from => [allowed_to, …].
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED = [
        Order::STATE_CART => [Order::STATE_DRAFT, Order::STATE_PLACED, Order::STATE_CANCELLED],
        Order::STATE_DRAFT => [Order::STATE_PLACED, Order::STATE_CANCELLED],
        Order::STATE_PLACED => [Order::STATE_PAID, Order::STATE_PARTIALLY_FULFILLED, Order::STATE_FULFILLED, Order::STATE_CANCELLED, Order::STATE_REFUNDED],
        Order::STATE_PAID => [Order::STATE_PARTIALLY_FULFILLED, Order::STATE_FULFILLED, Order::STATE_REFUNDED],
        Order::STATE_PARTIALLY_FULFILLED => [Order::STATE_FULFILLED, Order::STATE_REFUNDED],
        Order::STATE_FULFILLED => [Order::STATE_CLOSED, Order::STATE_REFUNDED],
        Order::STATE_CLOSED => [],
        Order::STATE_CANCELLED => [],
        Order::STATE_REFUNDED => [Order::STATE_CLOSED],
    ];

    public function __construct(private readonly Order $order) {}

    public function can(string $to): bool
    {
        return in_array($to, self::ALLOWED[$this->order->state] ?? [], true);
    }

    public function assertCan(string $to): void
    {
        if (! $this->can($to)) {
            throw new InvalidTransitionException(
                "Cannot transition order {$this->order->number} from {$this->order->state} → {$to}."
            );
        }
    }

    public function allowedNext(): array
    {
        return self::ALLOWED[$this->order->state] ?? [];
    }

    /**
     * @return array<int,string>
     */
    public static function states(): array
    {
        return [
            Order::STATE_CART, Order::STATE_DRAFT, Order::STATE_PLACED, Order::STATE_PAID,
            Order::STATE_PARTIALLY_FULFILLED, Order::STATE_FULFILLED,
            Order::STATE_CLOSED, Order::STATE_CANCELLED, Order::STATE_REFUNDED,
        ];
    }

    /**
     * @return array<string, array<int,string>>
     */
    public static function graph(): array
    {
        return self::ALLOWED;
    }

    public function recordTransition(string $to, ?Model $actor = null, ?string $reason = null): void
    {
        $this->order->state = $to;
        $this->order->save();
        $this->order->transitions()->create([
            'from' => $this->order->getOriginal('state') ?: $to,
            'to' => $to,
            'reason' => $reason,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);
        $this->order->recordEvent("order.{$to}", ['reason' => $reason]);
    }
}
