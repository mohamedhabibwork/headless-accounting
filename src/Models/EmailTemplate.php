<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use BelongsToCompany;

    protected $table = 'aut_email_templates';

    protected $fillable = [
        'company_id', 'code', 'subject', 'body',
        'placeholders', 'active',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'active' => 'boolean',
    ];
}
