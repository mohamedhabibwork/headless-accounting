<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use BelongsToCompany;

    protected $table = 'sub_plans';

    protected $fillable = [
        'company_id', 'name', 'description', 'currency', 'price_minor',
        'interval', 'interval_count', 'trial_days', 'active',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'interval_count' => 'integer',
        'trial_days' => 'integer',
        'active' => 'boolean',
    ];
}
