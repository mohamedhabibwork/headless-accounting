<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCreditNote extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'vendor_credit_notes';

    protected $fillable = [
        'company_id', 'vendor_id', 'bill_id', 'number',
        'currency', 'amount_minor', 'reason', 'state', 'issued_at',
    ];

    protected $casts = ['amount_minor' => 'integer', 'issued_at' => 'date'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
