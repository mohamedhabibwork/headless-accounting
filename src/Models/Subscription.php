<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToCompany;

    protected $table = 'sub_subscriptions';

    protected $fillable = [
        'company_id', 'plan_id', 'customer_id', 'starts_at',
        'trial_ends_at', 'current_period_starts_at',
        'current_period_ends_at', 'cancelled_at',
        'state', 'quantity', 'deferred_revenue_minor', 'currency',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'trial_ends_at' => 'date',
        'current_period_starts_at' => 'date',
        'current_period_ends_at' => 'date',
        'cancelled_at' => 'date',
        'quantity' => 'float',
        'deferred_revenue_minor' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
    }
}
