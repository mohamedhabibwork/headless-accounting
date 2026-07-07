<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountLimitation extends BaseModel
{
    protected string $tableSuffix = 'discount_limitations';

    protected $fillable = ['discount_id', 'type', 'config', 'position'];

    protected $casts = ['config' => 'array', 'position' => 'integer'];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
