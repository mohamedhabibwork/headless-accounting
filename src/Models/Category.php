<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends BaseModel
{
    protected string $tableSuffix = 'categories';

    protected $fillable = ['name', 'slug', 'description', 'position', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            Config::string('headless-accounting.table_prefix', 'ha_').'product_categories'
        );
    }
}
