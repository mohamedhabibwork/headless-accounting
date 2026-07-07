<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Fulfillment\CarrierRateShopper;
use Headless\Accounting\Models\ShippingRateCard;

/**
 * CarrierResolver — host-side contract for plugging a custom shipping
 * rate-shopper into the {@see CarrierRateShopper}
 * without subclassing the package's RateShopper.
 *
 * Hosts normally want this when they:
 *   - use a regional carrier the package does not ship with
 *   - have negotiated enterprise rates that are not in the RateCard
 *   - run their own routing optimisation service
 *
 * The `quote()` method receives a snapshot of the parcel + origin +
 * destination and returns a *list* of candidate quotes. The shape of
 * each quote mirrors the one {@see ShippingRateCard::quote()}
 * produces so the rest of the fulfillment pipeline can pick one
 * without modification:
 *
 *     [
 *         'carrier' => 'ups',
 *         'service' => 'express',
 *         'cost_minor' => 1299,
 *         'currency' => 'EUR',
 *         'eta_days_to' => 2,
 *         'tracking_url_template' => '...',
 *     ]
 */
interface CarrierResolver
{
    /**
     * @param  array{
     *     origin_country: string, origin_postal: ?string,
     *     destination_country: string, destination_postal: ?string,
     *     weight_grams: int, length_mm: ?int, width_mm: ?int, height_mm: ?int,
     *     quantity: int, declared_value_minor: ?int, currency: string,
     * }  $parcel
     * @return iterable<array<string,mixed>>
     */
    public function quote(array $parcel): iterable;

    /** Stable identifier (e.g. 'ups', 'dhl', 'in_house'). */
    public function name(): string;

    /** Whether the resolver is configured for production use. */
    public function isConfigured(): bool;
}
