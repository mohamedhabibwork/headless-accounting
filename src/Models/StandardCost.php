<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandardCost extends BaseModel
{
    protected string $tableSuffix = 'standard_costs';

    protected $fillable = ['variant_id', 'currency', 'unit_cost_minor', 'variance_pct', 'effective_from'];

    protected $casts = [
        'unit_cost_minor' => 'integer',
        'effective_from' => 'date',
        'variance_pct' => 'float',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
