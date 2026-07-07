<?php

declare(strict_types=1);

namespace Headless\Accounting\Sales;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturn extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'sales_returns';

    protected $fillable = [
        'company_id', 'customer_id', 'invoice_id', 'number',
        'return_date', 'currency', 'total_minor',
        'reason', 'state', 'lines',
    ];

    protected $casts = ['return_date' => 'date', 'lines' => 'array'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
