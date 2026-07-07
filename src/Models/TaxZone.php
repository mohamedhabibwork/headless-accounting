<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxZone extends BaseModel
{
    protected string $tableSuffix = 'tax_zones';

    protected $fillable = ['code', 'name', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function members(): HasMany
    {
        return $this->hasMany(TaxZoneMember::class, 'zone_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'zone_id');
    }
}
