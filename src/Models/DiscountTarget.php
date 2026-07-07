<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountTarget extends BaseModel
{
    protected string $tableSuffix = 'discount_targets';

    protected $fillable = ['discount_id', 'target_type', 'target_id'];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
