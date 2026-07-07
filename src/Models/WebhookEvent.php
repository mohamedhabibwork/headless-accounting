<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

class WebhookEvent extends BaseModel
{
    protected string $tableSuffix = 'webhook_events';

    protected $fillable = [
        'driver', 'provider_event_id', 'event_type',
        'payload', 'received_at', 'processed_at', 'outcome',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
