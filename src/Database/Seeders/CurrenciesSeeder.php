<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Seeders;

use Headless\Accounting\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * CurrenciesSeeder — bridges the static {@see \Headless\Accounting\Currency\Currency}
 * registry into the persisted `ha_currencies` table.
 */
class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (\Headless\Accounting\Currency\Currency::codes() as $code) {
            $meta = \Headless\Accounting\Currency\Currency::get($code);
            Currency::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $meta['name'],
                    'symbol' => $meta['symbol'],
                    'decimals' => $meta['decimals'],
                    'active' => true,
                ],
            );
        }
    }
}
