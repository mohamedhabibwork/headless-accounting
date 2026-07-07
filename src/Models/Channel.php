<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Channel extends BaseModel
{
    protected string $tableSuffix = 'channels';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code', 'name', 'currency', 'locale',
        'tax_zone_code', 'tax_inclusive', 'active', 'config',
    ];

    protected $casts = [
        'active' => 'boolean',
        'tax_inclusive' => 'boolean',
        'config' => 'array',
    ];

    protected function currency(): Attribute
    {
        return Attribute::make(get: fn () => $this->attributes['currency']);
    }
}
