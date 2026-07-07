<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalInstance extends Model
{
    use BelongsToCompany;

    protected $table = 'wf_approval_instances';

    protected $fillable = [
        'definition_id', 'company_id', 'subject_type', 'subject_id',
        'state', 'current_step',
    ];

    protected $casts = ['current_step' => 'integer'];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'definition_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'instance_id')->orderBy('decision_at');
    }
}
