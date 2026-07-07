<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Support\RoundingMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends BaseModel
{
    protected string $tableSuffix = 'tax_rates';

    protected $fillable = [
        'zone_id', 'tax_class_id', 'name',
        'percent', 'compound',
        'priority', 'active',
    ];

    protected $casts = [
        'percent' => 'float',
        'compound' => 'boolean',
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'zone_id');
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class, 'tax_class_id');
    }

    /**
     * Applies this rate to a subtotal in minor units and returns tax in minor units.
     */
    public function calculateTax(int $subtotalMinor, int $alreadyTaxedMinor = 0, string $rounding = 'half_even'): int
    {
        $base = $this->compound ? ($subtotalMinor + $alreadyTaxedMinor) : $subtotalMinor;
        $tax = ($base * $this->percent) / 100;

        return match ($rounding) {
            'half_even' => (int) RoundingMode::HalfEven->round($tax),
            'half_up' => (int) RoundingMode::HalfUp->round($tax),
            'down' => (int) floor($tax),
            'up' => (int) ceil($tax),
            default => (int) RoundingMode::HalfEven->round($tax),
        };
    }
}
