<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;

class ProfitCenter extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'profit_centers';

    protected $fillable = ['company_id', 'code', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];
}
