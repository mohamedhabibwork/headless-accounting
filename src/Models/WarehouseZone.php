<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseZone extends BaseModel
{
    use HasFactory;

    protected string $tableSuffix = 'warehouse_zones';

    protected $fillable = [
        'warehouse_id', 'code', 'name', 'kind',
        'is_default_pick', 'is_default_pack', 'position',
    ];

    protected $casts = [
        'is_default_pick' => 'boolean',
        'is_default_pack' => 'boolean',
        'position' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function bins(): HasMany
    {
        return $this->hasMany(WarehouseBin::class, 'zone_id');
    }
}
