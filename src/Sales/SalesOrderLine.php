<?php

declare(strict_types=1);

namespace Headless\Accounting\Sales;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends BaseModel
{
    protected string $tableSuffix = 'sales_order_lines';

    protected $fillable = [
        'sales_order_id', 'product_id', 'variant_id', 'description',
        'quantity', 'unit_price_minor',
        'discount_minor', 'tax_percent',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function lineSubtotal(): int
    {
        return (int) $this->unit_price_minor * (int) $this->quantity;
    }
}
