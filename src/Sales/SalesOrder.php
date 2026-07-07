<?php

declare(strict_types=1);

namespace Headless\Accounting\Sales;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends BaseModel
{
    use BelongsToCompany, SoftDeletes;

    protected string $tableSuffix = 'sales_orders';

    protected $fillable = [
        'company_id', 'customer_id', 'quotation_id', 'number',
        'currency', 'state', 'order_date', 'expected_ship_date',
        'warehouse_id', 'shipping_address',
        'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_ship_date' => 'date',
        'shipping_address' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }
}
