<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountUsage extends BaseModel
{
    protected string $tableSuffix = 'discount_usages';

    protected $fillable = [
        'discount_id', 'customer_id',
        'source_type', 'source_id',
        'amount_minor', 'currency', 'used_at',
    ];

    protected $casts = ['used_at' => 'datetime'];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
