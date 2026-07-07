<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransfer extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'inventory_transfers';

    protected $fillable = [
        'company_id', 'number',
        'from_location_id', 'to_location_id',
        'transferred_at', 'state', 'lines', 'journal_entry_id',
    ];

    protected $casts = ['transferred_at' => 'date', 'lines' => 'array'];

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
