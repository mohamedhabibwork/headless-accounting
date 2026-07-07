<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransfer extends BaseModel
{
    protected string $tableSuffix = 'asset_transfers';

    protected $fillable = [
        'asset_id', 'from_location_id', 'to_location_id',
        'from_custodian_id', 'to_custodian_id',
        'transferred_at', 'reason',
    ];

    protected $casts = ['transferred_at' => 'date'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
