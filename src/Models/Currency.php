<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Currency\Currency as CurrencyRegistry;

class Currency extends BaseModel
{
    protected string $tableSuffix = 'currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'symbol', 'decimals', 'active'];

    protected $casts = ['active' => 'boolean'];

    public static function registry(): array
    {
        $codes = CurrencyRegistry::codes();
        $stored = static::query()->whereIn('code', $codes)->get()->keyBy('code');

        return collect($codes)->mapWithKeys(fn ($c) => [
            $c => $stored[$c] ?? null,
        ])->all();
    }

    public function exists(): bool
    {
        return CurrencyRegistry::exists($this->code);
    }
}
