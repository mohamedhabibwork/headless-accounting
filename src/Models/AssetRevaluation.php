<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRevaluation extends BaseModel
{
    protected string $tableSuffix = 'asset_revaluations';

    protected $fillable = [
        'asset_id', 'revalued_at',
        'previous_cost_minor', 'new_cost_minor',
        'revaluation_reserve_delta_minor',
        'journal_entry_id', 'reason',
    ];

    protected $casts = ['revalued_at' => 'date'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
