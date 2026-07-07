<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    use BelongsToCompany;

    protected $table = 'int_api_clients';

    protected $fillable = [
        'company_id', 'name', 'client_id', 'secret_hash',
        'scopes', 'active', 'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];
}
