<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditEvent extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'audit_events';

    protected $fillable = [
        'company_id', 'subject_type', 'subject_id',
        'event', 'actor_type', 'actor_id',
        'ip', 'before', 'after', 'metadata', 'occurred_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(
        Model $subject,
        string $event,
        ?array $before = null,
        ?array $after = null,
        mixed $actor = null,
        ?string $ip = null,
        array $metadata = [],
    ): self {
        return static::create([
            'company_id' => CompanyContext::id() ?? null,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'event' => $event,
            'before' => $before,
            'after' => $after,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey() ? (string) $actor->getKey() : null,
            'ip' => $ip ?? request()?->ip(),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
