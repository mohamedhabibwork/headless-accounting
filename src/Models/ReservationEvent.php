<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReservationEvent — audit row for one lifecycle event on a
 * {@see StockReservation} (created, released, expired, fulfilled, …).
 */
class ReservationEvent extends BaseModel
{
    protected string $tableSuffix = 'reservation_events';

    protected $fillable = [
        'stock_reservation_id', 'event',
        'quantity_delta', 'note', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'quantity_delta' => 'integer',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(StockReservation::class, 'stock_reservation_id');
    }
}
