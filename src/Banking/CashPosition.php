<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;

class CashPosition extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'cash_positions';

    protected $fillable = ['company_id', 'as_of', 'currency', 'snapshot'];

    protected $casts = ['as_of' => 'date', 'snapshot' => 'array'];
}
