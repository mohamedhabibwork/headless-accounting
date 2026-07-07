<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'production_orders';

    protected $fillable = [
        'company_id', 'number', 'bom_id',
        'quantity_to_produce', 'scheduled_date', 'state',
        'journal_entry_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'quantity_to_produce' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }
}
