<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostLayer extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'cost_layers';

    protected $fillable = [
        'company_id', 'variant_id', 'location_id',
        'received_at', 'manufacturing_date', 'expiration_date', 'batch_number',
        'quantity_received', 'quantity_remaining',
        'unit_cost_minor', 'currency', 'source',
        'source_document_type', 'source_document_id',
    ];

    protected $casts = [
        'received_at' => 'date',
        'manufacturing_date' => 'date',
        'expiration_date' => 'date',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
        'unit_cost_minor' => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sourceDocument(): MorphTo
    {
        return $this->morphTo();
    }

    public function totalRemainingCost(): int
    {
        return (int) $this->quantity_remaining * (int) $this->unit_cost_minor;
    }

    public function scopeFefo($query)
    {
        return $query->orderByRaw('expiration_date IS NULL, expiration_date ASC')->orderBy('received_at');
    }

    public function scopeFifo($query)
    {
        return $query->orderBy('received_at');
    }

    public function scopeForBatch($query, string $batchNumber)
    {
        return $query->where('batch_number', $batchNumber);
    }
}
