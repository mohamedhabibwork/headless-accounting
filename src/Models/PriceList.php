<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends BaseModel
{
    protected string $tableSuffix = 'price_lists';

    protected $fillable = [
        'name', 'code', 'scope', 'scope_ref',
        'currency', 'valid_from', 'valid_until',
        'priority', 'active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'price_list_id');
    }
}
