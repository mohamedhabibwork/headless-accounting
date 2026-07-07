<?php

declare(strict_types=1);

namespace Headless\Accounting\Sales;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Models\Location;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNote extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'delivery_notes';

    protected $fillable = [
        'company_id', 'sales_order_id', 'warehouse_id', 'number',
        'ship_date', 'state', 'lines',
    ];

    protected $casts = [
        'ship_date' => 'date',
        'lines' => 'array',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'warehouse_id');
    }
}
