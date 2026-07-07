<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends BaseModel
{
    protected string $tableSuffix = 'asset_disposals';

    protected $fillable = [
        'asset_id', 'disposed_at', 'method',
        'proceeds_minor', 'cost_at_disposal_minor',
        'accumulated_at_disposal_minor', 'gain_loss_minor',
        'journal_entry_id', 'notes',
    ];

    protected $casts = [
        'disposed_at' => 'date',
        'proceeds_minor' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
