<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends BaseModel
{
    protected string $tableSuffix = 'bom_lines';

    protected $fillable = ['bom_id', 'component_id', 'quantity', 'scrap_pct'];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }
}
