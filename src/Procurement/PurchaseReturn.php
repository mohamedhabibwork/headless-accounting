<?php

declare(strict_types=1);

namespace Headless\Accounting\Procurement;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Bill;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturn extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'purchase_returns';

    protected $fillable = [
        'company_id', 'vendor_id', 'bill_id', 'number',
        'return_date', 'currency', 'total_minor', 'reason', 'state', 'lines',
    ];

    protected $casts = ['return_date' => 'date', 'lines' => 'array'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
