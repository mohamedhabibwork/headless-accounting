<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlan extends Model
{
    protected $table = 'saas_plans';

    protected $fillable = [
        'code', 'name', 'price_monthly',
        'features', 'limits', 'active',
    ];

    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'active' => 'boolean',
        'price_monthly' => 'float',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class, 'plan_id');
    }
}
