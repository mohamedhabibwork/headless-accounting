<?php

declare(strict_types=1);

namespace Headless\Accounting\Procurement;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GoodsReceipt — records the receipt of goods against a PurchaseOrder,
 * posts a perpetual inventory entry (Dr Inventory Asset / Cr AP or
 * Goods-In-Transit), and increases stock on hand.
 */
class GoodsReceipt extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'goods_receipts';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'vendor_id', 'warehouse_id',
        'number', 'received_at', 'state', 'lines', 'journal_entry_id',
    ];

    protected $casts = ['received_at' => 'date', 'lines' => 'array'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'warehouse_id');
    }
}
