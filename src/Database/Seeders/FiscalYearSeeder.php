<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Seeders;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\AccountingPeriod;
use Headless\Accounting\Models\FiscalYear;
use Illuminate\Database\Seeder;

/**
 * FiscalYearSeeder — provisions one fiscal year, twelve monthly periods.
 */
class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) date('Y');
        $fy = FiscalYear::query()->updateOrCreate(
            ['name' => (string) $year],
            ['starts_at' => "{$year}-01-01", 'ends_at' => "{$year}-12-31"],
        );

        $cursor = CarbonImmutable::create($year, 1, 1);
        for ($m = 1; $m <= 12; $m++) {
            AccountingPeriod::query()->updateOrCreate(
                ['code' => sprintf('%d-%02d', $year, $m)],
                [
                    'fiscal_year_id' => $fy->id,
                    'starts_at' => $cursor->startOfMonth()->toDateString(),
                    'ends_at' => $cursor->endOfMonth()->toDateString(),
                ],
            );
            $cursor = $cursor->addMonth();
        }
    }
}
