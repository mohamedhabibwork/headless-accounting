<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends BaseModel
{
    use HasFactory;

    protected string $tableSuffix = 'carriers';

    protected $fillable = [
        'code', 'name', 'tracking_url_template',
        'service_levels', 'credentials',
        'sandbox', 'active',
    ];

    protected $casts = [
        'service_levels' => 'array',
        'credentials' => 'array',
        'sandbox' => 'boolean',
        'active' => 'boolean',
    ];

    public function rateCards(): HasMany
    {
        return $this->hasMany(ShippingRateCard::class, 'carrier_id');
    }

    public function activeRateCards(): HasMany
    {
        return $this->rateCards()->where('active', true);
    }

    public function findService(string $serviceCode): ?array
    {
        foreach ((array) $this->service_levels as $level) {
            if (($level['code'] ?? null) === $serviceCode) {
                return $level;
            }
        }

        return null;
    }
}
