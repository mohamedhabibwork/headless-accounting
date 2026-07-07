<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends BaseModel
{
    use BelongsToCompany, SoftDeletes;

    protected string $tableSuffix = 'vendors';

    protected $fillable = [
        'company_id', 'code', 'name', 'legal_name',
        'email', 'phone', 'contact_name',
        'tax_id', 'iban', 'bic',
        'default_currency', 'default_locale',
        'credit_limit_minor', 'currency',
        'payment_terms_days', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'credit_limit_minor' => 'integer',
    ];

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(VendorAddress::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(VendorCreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(VendorDebitNote::class);
    }

    public function balance(string $currency): int
    {
        return (int) $this->bills()
            ->where('currency', $currency)
            ->sum('balance_minor');
    }
}
