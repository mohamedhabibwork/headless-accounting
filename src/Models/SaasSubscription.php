<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasSubscription extends Model
{
    protected $table = 'saas_subscriptions';

    protected $fillable = [
        'plan_id', 'company_id', 'started_at',
        'renews_at', 'trial_ends_at',
        'state', 'modules_enabled',
    ];

    protected $casts = [
        'started_at' => 'date',
        'renews_at' => 'date',
        'trial_ends_at' => 'date',
        'modules_enabled' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class);
    }
}
