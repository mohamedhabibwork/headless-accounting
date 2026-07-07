<?php

declare(strict_types=1);

namespace Headless\Accounting\Sales;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends BaseModel
{
    use BelongsToCompany, SoftDeletes;

    protected string $tableSuffix = 'quotations';

    protected $fillable = [
        'company_id', 'customer_id', 'number', 'currency', 'state',
        'issue_date', 'expiry_date',
        'shipping_address', 'lines',
        'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'shipping_address' => 'array',
        'lines' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
