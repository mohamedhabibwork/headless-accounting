<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends BaseModel
{
    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_EXPENSE = 'expense';

    protected string $tableSuffix = 'accounts';

    protected $fillable = ['code', 'name', 'type', 'parent_id', 'currency', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class, 'account_id');
    }

    /**
     * Aggregated balance for a specific currency. Debits increase assets
     * and expenses, credits increase liability/equity/revenue.
     */
    public function balance(string $currency): int
    {
        $debit = (int) $this->postings()->where('currency', $currency)->sum('debit_minor');
        $credit = (int) $this->postings()->where('currency', $currency)->sum('credit_minor');

        return match ($this->type) {
            self::TYPE_ASSET, self::TYPE_EXPENSE => $debit - $credit,
            default => $credit - $debit,
        };
    }
}
