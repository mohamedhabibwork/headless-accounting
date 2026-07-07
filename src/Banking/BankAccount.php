<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Headless\Accounting\Contracts\Bankable;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends BaseModel implements Bankable
{
    use BelongsToCompany;

    protected string $tableSuffix = 'bank_accounts';

    protected $fillable = [
        'company_id', 'code', 'name', 'chart_account_id',
        'currency', 'iban', 'bic', 'bank_name',
        'is_default', 'active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'chart_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function outstandingChecks(): HasMany
    {
        return $this->hasMany(OutstandingCheck::class);
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(BankTransfer::class, 'to_account_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(BankTransfer::class, 'from_account_id');
    }

    // — Bankable —
    public function iban(): ?string
    {
        return $this->attributes['iban'] ?? null;
    }

    public function bic(): ?string
    {
        return $this->attributes['bic'] ?? null;
    }

    public function currency(): string
    {
        return (string) ($this->attributes['currency'] ?? '');
    }

    public function isDefault(): bool
    {
        return (bool) ($this->attributes['is_default'] ?? false);
    }
}
