<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDebitNote extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'customer_debit_notes';

    protected $fillable = [
        'company_id', 'customer_id', 'invoice_id', 'number',
        'currency', 'amount_minor', 'reason', 'state', 'issued_at',
    ];

    protected $casts = ['amount_minor' => 'integer', 'issued_at' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
