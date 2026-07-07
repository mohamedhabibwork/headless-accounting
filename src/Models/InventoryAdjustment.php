<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'inventory_adjustments';

    protected $fillable = [
        'company_id', 'number', 'location_id',
        'adjusted_at', 'reason', 'lines', 'notes', 'journal_entry_id',
    ];

    protected $casts = ['adjusted_at' => 'date', 'lines' => 'array'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
