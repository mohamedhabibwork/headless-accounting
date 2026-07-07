<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;

class BusinessUnit extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'business_units';

    protected $fillable = ['company_id', 'code', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];
}
