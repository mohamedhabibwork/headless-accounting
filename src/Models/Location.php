<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends BaseModel
{
    protected string $tableSuffix = 'locations';

    protected $fillable = ['code', 'name', 'type', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'location_id');
    }
}
