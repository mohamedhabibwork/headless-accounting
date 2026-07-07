<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $table = 'sub_invoices';

    protected $fillable = [
        'subscription_id', 'invoice_id', 'issue_at', 'currency',
        'amount_minor', 'recognized_minor', 'state',
    ];

    protected $casts = [
        'issue_at' => 'date',
        'amount_minor' => 'integer',
        'recognized_minor' => 'integer',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
