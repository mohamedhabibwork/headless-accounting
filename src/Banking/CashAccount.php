<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAccount extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'cash_accounts';

    protected $fillable = ['company_id', 'code', 'name', 'chart_account_id', 'currency', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'chart_account_id');
    }
}
