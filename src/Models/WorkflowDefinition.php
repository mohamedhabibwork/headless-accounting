<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    use BelongsToCompany;

    protected $table = 'wf_definitions';

    protected $fillable = ['company_id', 'scope', 'name', 'description', 'config', 'active'];

    protected $casts = ['active' => 'boolean', 'config' => 'array'];

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class, 'definition_id')->orderBy('order');
    }
}
