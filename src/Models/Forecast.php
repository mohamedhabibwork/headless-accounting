<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Forecast extends BaseModel
{
    protected string $tableSuffix = 'forecasts';

    protected $fillable = ['budget_id', 'account_id', 'month', 'forecast_minor', 'confidence'];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
