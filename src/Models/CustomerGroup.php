<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerGroup extends BaseModel
{
    protected string $tableSuffix = 'customer_groups';

    protected $fillable = ['name', 'code', 'description', 'tax_exempt'];

    protected $casts = ['tax_exempt' => 'boolean'];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            Config::string('headless-accounting.table_prefix', 'ha_').'customer_group_members'
        );
    }
}
