<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLine extends BaseModel
{
    protected string $tableSuffix = 'bill_lines';

    protected $fillable = [
        'bill_id', 'product_id', 'description',
        'quantity', 'unit_cost_minor',
        'currency', 'tax_percent', 'tax_rate_id', 'account_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'tax_percent' => 'float',
        'unit_cost_minor' => 'integer',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function lineSubtotal(): int
    {
        return (int) $this->unit_cost_minor * (int) $this->quantity;
    }
}
