<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PackStation — records the packing step: carton dimensions/weight,
 * packer, and item verification. The output becomes a {@see Shipment}.
 */
class PackStation extends BaseModel
{
    public const STATE_OPEN = 'open';

    public const STATE_PACKED = 'packed';

    public const STATE_SEALED = 'sealed';

    public const STATE_SHIPPED = 'shipped';

    protected string $tableSuffix = 'pack_stations';

    protected $fillable = [
        'pick_list_id', 'number',
        'packer_name', 'carton_type',
        'weight_grams', 'length_mm', 'width_mm', 'height_mm',
        'items', 'state',
        'packed_at', 'sealed_at',
    ];

    protected $casts = [
        'items' => 'array',
        'weight_grams' => 'float',
        'length_mm' => 'float',
        'width_mm' => 'float',
        'height_mm' => 'float',
        'packed_at' => 'datetime',
        'sealed_at' => 'datetime',
    ];

    public function pickList(): BelongsTo
    {
        return $this->belongsTo(PickList::class, 'pick_list_id');
    }

    public function totalItems(): int
    {
        $total = 0;
        foreach ((array) $this->items as $line) {
            $total += (int) ($line['quantity'] ?? 0);
        }

        return $total;
    }

    public function volumetricWeightGrams(float $divisor = 5000.0): float
    {
        if (! $this->length_mm || ! $this->width_mm || ! $this->height_mm) {
            return 0.0;
        }

        return ($this->length_mm * $this->width_mm * $this->height_mm) / $divisor;
    }

    public function billableWeightGrams(float $divisor = 5000.0): float
    {
        $vol = $this->volumetricWeightGrams($divisor);

        return max((float) $this->weight_grams, $vol);
    }
}
