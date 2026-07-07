<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowDelegation extends Model
{
    protected $table = 'wf_delegations';

    protected $fillable = [
        'from_user_id', 'to_user_id',
        'scope_type', 'scope_id',
        'starts_at', 'ends_at', 'active',
    ];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'active' => 'boolean'];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_user_id');
    }

    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}
