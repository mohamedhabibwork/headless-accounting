<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    use BelongsToCompany;

    protected $table = 'aut_scheduled_reports';

    protected $fillable = [
        'company_id', 'name', 'report_code',
        'filters', 'frequency', 'recipients', 'format', 'active',
    ];

    protected $casts = [
        'filters' => 'array',
        'recipients' => 'array',
        'active' => 'boolean',
    ];
}
