<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Actions\Order\ApplyDiscount;
use Headless\Accounting\Models\OrderItem;

/**
 * DiscountDriver — the polymorphic strategy that decides *how much*
 * of a discount applies given an EvaluationContext and the candidate
 * lines (or a single OrderItem/OrderSubject).
 *
 * Drivers MUST:
 *   1. Be stateless beyond the `config()` they receive.
 *   2. Return a {@see DiscountApplication} with `Money` in the same
 *      currency as the candidate subtotal.
 *   3. Never mutate the database. Side effects belong to the
 *      {@see ApplyDiscount} action.
 */
interface DiscountDriver
{
    /**
     * Calculate the discount to apply to a set of candidate lines.
     *
     * @param  iterable<OrderItem>  $candidateLines
     */
    public function calculate(EvaluationContext $ctx, iterable $candidateLines): DiscountApplication;

    /** Receives the per-discount config array (from `config` JSON column). */
    public function setConfig(array $config): void;

    /** Driver identification, e.g. 'percentage', 'fixed', 'buy_x_get_y'. */
    public function type(): string;
}
