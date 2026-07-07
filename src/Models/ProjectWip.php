<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWip extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'project_wip';

    protected $fillable = [
        'company_id', 'project_id', 'as_of', 'currency',
        'costs_minor', 'recognized_revenue_minor', 'over_under_minor',
    ];

    protected $casts = ['as_of' => 'date'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
