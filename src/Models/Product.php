<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Contracts\Stockable;
use Headless\Accounting\Contracts\Taxable;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel implements Stockable, Taxable
{
    use HasFactory, SoftDeletes;

    public const TYPE_PRODUCT = 'product';

    public const TYPE_RAW_MATERIAL = 'raw_material';

    public const TYPE_FINISHED_GOOD = 'finished_good';

    public const TYPE_SEMI_FINISHED = 'semi_finished';

    public const TYPE_SERVICE = 'service';

    public const TYPE_CONSUMABLE = 'consumable';

    public const TYPE_SPARE_PART = 'spare_part';

    public const TYPE_KIT = 'kit';

    protected string $tableSuffix = 'products';

    protected $fillable = [
        'name', 'slug', 'description', 'sku',
        'tax_class_id', 'stock_tracked', 'active', 'attributes',
        'item_type', 'batch_tracked', 'serial_tracked', 'expiration_tracked',
        'unit_of_measure', 'hazard_class', 'temperature_class',
    ];

    protected $casts = [
        'stock_tracked' => 'boolean',
        'active' => 'boolean',
        'attributes' => 'array',
        'item_type' => 'string',
        'batch_tracked' => 'boolean',
        'serial_tracked' => 'boolean',
        'expiration_tracked' => 'boolean',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            Config::string('headless-accounting.table_prefix', 'ha_').'product_categories'
        );
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'subject');
    }

    public function discountTargets(): MorphMany
    {
        return $this->morphMany(DiscountTarget::class, 'target');
    }

    public function productBarcodes(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductBarcode::class,
            ProductVariant::class,
            'product_id',
            'variant_id',
            'id',
            'id',
        );
    }

    public function batches(): HasManyThrough
    {
        return $this->hasManyThrough(
            Batch::class,
            ProductVariant::class,
            'product_id',
            'variant_id',
            'id',
            'id',
        );
    }

    public function taxClassId(): ?int
    {
        return $this->tax_class_id;
    }

    public function taxContext(): array
    {
        return [
            'digital' => (bool) ($this->attributes['digital'] ?? false),
        ];
    }

    public function isStockTracked(): bool
    {
        return (bool) $this->stock_tracked;
    }

    public function isBatchTracked(): bool
    {
        return (bool) $this->batch_tracked;
    }

    public function isSerialTracked(): bool
    {
        return (bool) $this->serial_tracked;
    }

    public function isExpirationTracked(): bool
    {
        return (bool) $this->expiration_tracked;
    }
}
