<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenance extends BaseModel
{
    protected string $tableSuffix = 'asset_maintenance';

    protected $fillable = [
        'asset_id', 'service_at', 'action',
        'cost_minor', 'currency', 'vendor_id', 'notes',
    ];

    protected $casts = [
        'service_at' => 'date',
        'cost_minor' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
