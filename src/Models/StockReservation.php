<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockReservation extends BaseModel
{
    protected string $tableSuffix = 'stock_reservations';

    protected $fillable = [
        'stock_item_id', 'source_type', 'source_id',
        'quantity', 'expires_at',
        'batch_number', 'serial_number', 'expiration_date', 'priority',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'expiration_date' => 'date',
        'priority' => 'integer',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReservationEvent::class, 'stock_reservation_id')->orderBy('occurred_at', 'desc');
    }
}
