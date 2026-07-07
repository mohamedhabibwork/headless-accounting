<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchStock extends BaseModel
{
    protected string $tableSuffix = 'batch_stocks';

    protected $fillable = [
        'batch_id', 'location_id', 'bin_id',
        'quantity', 'reserved',
        'currency', 'unit_cost_minor',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved' => 'integer',
        'unit_cost_minor' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }
}
