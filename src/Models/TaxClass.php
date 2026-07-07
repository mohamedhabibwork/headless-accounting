<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxClass extends BaseModel
{
    protected string $tableSuffix = 'tax_classes';

    protected $fillable = ['name', 'slug', 'description'];

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'tax_class_id');
    }
}
