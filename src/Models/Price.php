<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Currency\Money;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Price extends BaseModel
{
    protected string $tableSuffix = 'prices';

    protected $fillable = [
        'price_list_id', 'subject_type', 'subject_id',
        'amount_minor', 'currency',
        'compare_at_minor', 'min_quantity',
        'tax_inclusive',
        'valid_from', 'valid_until',
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'tax_inclusive' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function money(): Money
    {
        return new Money((int) $this->amount_minor, $this->currency);
    }

    public function compareAt(): ?Money
    {
        if ($this->compare_at_minor === null) {
            return null;
        }

        return new Money((int) $this->compare_at_minor, $this->currency);
    }
}
