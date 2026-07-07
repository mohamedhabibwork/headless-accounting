<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'boms';

    protected $fillable = ['company_id', 'product_id', 'code', 'name', 'quantity_per_unit', 'active'];

    protected $casts = ['quantity_per_unit' => 'integer', 'active' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class);
    }
}
