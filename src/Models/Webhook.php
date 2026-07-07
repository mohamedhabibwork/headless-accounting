<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use BelongsToCompany;

    protected $table = 'int_webhooks';

    protected $fillable = [
        'company_id', 'name', 'url', 'secret',
        'event_types', 'content_type', 'active',
    ];

    protected $casts = [
        'event_types' => 'array',
        'active' => 'boolean',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
