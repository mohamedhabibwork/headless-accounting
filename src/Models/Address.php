<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends BaseModel
{
    protected string $tableSuffix = 'addresses';

    protected $fillable = [
        'owner_type', 'owner_id', 'type',
        'company', 'first_name', 'last_name',
        'line1', 'line2', 'city', 'region',
        'postal_code', 'country_code',
        'phone', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function formatted(): array
    {
        return [
            'company' => $this->company,
            'name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postal_code,
            'country' => $this->country_code,
            'phone' => $this->phone,
        ];
    }
}
