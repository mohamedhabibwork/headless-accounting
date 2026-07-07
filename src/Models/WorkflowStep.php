<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    protected $table = 'wf_steps';

    protected $fillable = [
        'definition_id', 'order', 'name',
        'approver_type', 'approver_config',
        'min_amount_minor', 'max_amount_minor',
        'mode', 'required',
    ];

    protected $casts = [
        'approver_config' => 'array',
        'min_amount_minor' => 'integer',
        'max_amount_minor' => 'integer',
        'required' => 'boolean',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'definition_id');
    }
}
