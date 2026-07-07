<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRateCard extends BaseModel
{
    use HasFactory;

    protected string $tableSuffix = 'shipping_rate_cards';

    protected $fillable = [
        'carrier_id', 'warehouse_id',
        'service_code', 'service_name',
        'destinations',
        'min_weight_grams', 'max_weight_grams',
        'base_cost_minor', 'per_kg_cost_minor',
        'currency',
        'free_shipping_threshold_minor',
        'eta_days_from', 'eta_days_to',
        'priority', 'active',
    ];

    protected $casts = [
        'destinations' => 'array',
        'min_weight_grams' => 'float',
        'max_weight_grams' => 'float',
        'base_cost_minor' => 'integer',
        'per_kg_cost_minor' => 'integer',
        'free_shipping_threshold_minor' => 'integer',
        'eta_days_from' => 'integer',
        'eta_days_to' => 'integer',
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /** Quote the rate for a destination and total weight (grams). */
    public function quote(string $destinationCountry, float $weightGrams, int $itemsValueMinor = 0): ?array
    {
        if (! $this->appliesTo($destinationCountry, $weightGrams)) {
            return null;
        }

        $baseCost = (int) $this->base_cost_minor;
        $perKg = (int) $this->per_kg_cost_minor;

        $weightKg = max(0.0, $weightGrams) / 1000.0;
        $cost = (int) round($baseCost + $perKg * $weightKg);

        if ($this->free_shipping_threshold_minor !== null
            && $itemsValueMinor >= (int) $this->free_shipping_threshold_minor) {
            $cost = 0;
        }

        return [
            'carrier_id' => $this->carrier_id,
            'carrier_code' => $this->carrier?->code,
            'service_code' => $this->service_code,
            'service_name' => $this->service_name,
            'currency' => $this->currency,
            'cost_minor' => $cost,
            'eta_days_from' => $this->eta_days_from,
            'eta_days_to' => $this->eta_days_to,
            'priority' => $this->priority,
        ];
    }

    public function appliesTo(string $destinationCountry, float $weightGrams): bool
    {
        $dests = (array) $this->destinations;
        if (! empty($dests)
            && ! in_array('*', $dests, true)
            && ! in_array(strtoupper($destinationCountry), array_map('strtoupper', $dests), true)) {
            return false;
        }

        if ($weightGrams < (float) $this->min_weight_grams) {
            return false;
        }

        if ($this->max_weight_grams !== null && $weightGrams > (float) $this->max_weight_grams) {
            return false;
        }

        return true;
    }
}
