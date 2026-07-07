<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\ReservationExpired;
use Headless\Accounting\Models\ReservationEvent;
use Headless\Accounting\Models\StockReservation;
use Illuminate\Support\Facades\Event;

/**
 * ReleaseExpiredReservation — sweeps {@see StockReservation} rows whose
 * `expires_at` has passed, releases the reserved quantity on the
 * underlying StockItem, writes a ReservationEvent('expired') and removes
 * the reservation. Runs in bounded chunks to avoid loading every
 * expired reservation into memory.
 */
final class ReleaseExpiredReservation extends Action
{
    protected function handle(?int $limit = 500): int
    {
        $count = 0;

        StockReservation::query()
            ->where('expires_at', '<', now())
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->chunkById($limit, function ($reservations) use (&$count) {
                foreach ($reservations as $reservation) {
                    $stockItem = $reservation->stockItem;
                    if ($stockItem) {
                        $stockItem->reserved = max(0, (int) $stockItem->reserved - (int) $reservation->quantity);
                        $stockItem->save();
                    }

                    ReservationEvent::create([
                        'stock_reservation_id' => $reservation->id,
                        'event' => 'expired',
                        'quantity_delta' => -((int) $reservation->quantity),
                        'note' => 'Released by sweeper.',
                        'occurred_at' => now(),
                    ]);

                    Event::dispatch(new ReservationExpired($reservation));

                    $reservation->delete();
                    $count++;
                }
            });

        return $count;
    }
}
