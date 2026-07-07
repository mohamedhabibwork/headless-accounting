<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Models\Order;

/**
 * OrderSubject — host-side contract for any object that the package can
 * turn into an {@see Order}. The package
 * already ships with `Cart` as the canonical subject, but a host often
 * needs to derive orders from other sources:
 *
 *   - a Quotation the salesperson accepted
 *   - a Subscription renewal charge
 *   - a Booking the customer paid for at the front desk
 *   - a manually-created invoice for a service
 *
 * Host projects either:
 *   1. Adapt their subject into a `Cart` row (recommended), or
 *   2. Implement `OrderSubject` and use the package's
 *      `CreateOrderFromSubject` action.
 *
 * The interface deliberately stays small — it's a contract about
 * "what would an order look like?" rather than a hard behavioural
 * requirement.
 */
interface OrderSubject
{
    /** ISO-4217 currency the resulting order should use. */
    public function currency(): string;

    /** Channel code (matches `Channel.code`). */
    public function channel(): string;

    /** Locale to use for messaging and tax rules. */
    public function locale(): ?string;

    /**
     * Returns candidate line items, each as `['variant_id' => …,
     *  'quantity' => …,  'unit_price_minor' => …, 'currency' => …,
     *  'name' => …, 'sku' => …]`.
     *
     * @return iterable<array<string,mixed>>
     */
    public function candidateLines(): iterable;

    /** Optional pre-calculated shipping in minor units. */
    public function shippingMinor(): int;

    /** Optional pre-calculated discount total in minor units. */
    public function discountTotalMinor(): int;
}
