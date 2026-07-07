<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

class ExchangeRate extends BaseModel
{
    protected string $tableSuffix = 'exchange_rates';

    protected $fillable = [
        'base_currency', 'quote_currency', 'rate',
        'effective_at', 'source',
    ];

    protected $casts = [
        'rate' => 'float',
        'effective_at' => 'date',
    ];
}
