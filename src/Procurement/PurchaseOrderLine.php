<?php

declare(strict_types=1);

namespace Headless\Accounting\Procurement;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends BaseModel
{
    protected string $tableSuffix = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id', 'product_id', 'description',
        'quantity', 'unit_cost_minor', 'tax_percent',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
