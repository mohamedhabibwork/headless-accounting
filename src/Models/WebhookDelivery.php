<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $table = 'int_webhook_deliveries';

    protected $fillable = [
        'webhook_id', 'event_type', 'http_status',
        'payload', 'attempt', 'error', 'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
