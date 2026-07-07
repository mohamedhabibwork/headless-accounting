<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxZoneMember extends BaseModel
{
    protected string $tableSuffix = 'tax_zone_members';

    protected $fillable = [
        'zone_id', 'country_code', 'region',
        'postal_code_pattern', 'operator',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'zone_id');
    }

    public function matches(?string $country, ?string $region, ?string $postal): bool
    {
        if ($this->country_code && $this->country_code !== $country) {
            return false;
        }
        if ($this->region && $this->region !== $region) {
            return false;
        }
        if ($this->postal_code_pattern && $postal && ! fnmatch($this->postal_code_pattern, $postal)) {
            return false;
        }

        return true;
    }
}
