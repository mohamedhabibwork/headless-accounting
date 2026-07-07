<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderStateTransition extends BaseModel
{
    protected string $tableSuffix = 'order_state_transitions';

    protected $fillable = [
        'order_id', 'from', 'to', 'reason',
        'actor_type', 'actor_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
