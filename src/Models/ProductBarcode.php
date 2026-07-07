<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductBarcode — multi-barcode record for a {@see ProductVariant}
 * (EAN-13, UPC-A, GS1-128, CODE128, QR, etc.).
 */
class ProductBarcode extends BaseModel
{
    use HasFactory;

    protected string $tableSuffix = 'product_barcodes';

    protected $fillable = [
        'variant_id', 'barcode', 'symbology',
        'is_primary', 'label_template', 'active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'active' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function resolveBarcodeImage(): string
    {
        return 'data:image/png;base64,'.$this->barcode;
    }
}
