<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Exceptions\TaxResolverException;
use Headless\Accounting\Tax\TaxEngine;
use Headless\Accounting\Tax\TaxLine;

/**
 * TaxRateResolver — host-side contract for plugging an external
 * tax engine into the package without forking {@see TaxEngine}.
 *
 * A typical implementation delegates to:
 *   - Avalara
 *   - TaxJar
 *   - a custom in-house rules engine
 *
 * The package calls {@see TaxRateResolver::resolve()} inside
 * `CalculateOrderTotals` to enrich / override its own results. The
 * resolver MUST return Money in the order's currency, and at least one
 * TaxLine when a tax applies.
 *
 * Resolve on:
 *   - `null` (no tax registered yet) — return an empty array
 *   - a happy-path — return the lines to add to the breakdown
 *   - an error — throw {@see TaxResolverException}
 */
interface TaxRateResolver
{
    /**
     * @param  array{
     *     currency: string,
     *     billing_country: ?string,
     *     shipping_country: ?string,
     *     tax_class_id: ?int,
     *     tax_zone_code: ?string,
     *     lines: array<int, array{variant_id:?int, quantity:int, unit_price_minor:int, currency:string}>,
     * }  $context
     * @return iterable<TaxLine>
     */
    public function resolve(array $context): iterable;

    /** Stable identifier for the resolver (e.g. 'avalara', 'taxjar'). */
    public function name(): string;

    /** Whether the resolver is configured (has credentials, etc). */
    public function isConfigured(): bool;
}
