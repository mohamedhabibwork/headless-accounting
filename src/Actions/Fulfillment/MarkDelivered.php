<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Fulfillment;

use Carbon\Carbon;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Shipment;

/**
 * MarkDelivered — flips a {@see Shipment} to `delivered`. Triggers the
 * order state to `fulfilled` if every shipment for that order is
 * delivered.
 */
final class MarkDelivered extends Action
{
    protected function handle(Shipment $shipment, ?string $deliveredAt = null): Shipment
    {
        $shipment->state = Shipment::STATE_DELIVERED;
        $shipment->delivered_at = $deliveredAt ? Carbon::parse($deliveredAt) : now();
        $shipment->save();

        return $shipment;
    }
}
