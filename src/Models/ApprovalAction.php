<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalAction extends Model
{
    protected $table = 'wf_approval_actions';

    protected $fillable = [
        'instance_id', 'step_id', 'decision',
        'actor_type', 'actor_id', 'notes', 'decision_at',
    ];

    protected $casts = ['decision_at' => 'datetime'];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
