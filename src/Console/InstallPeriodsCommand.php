<?php

declare(strict_types=1);

namespace Headless\Accounting\Console;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\AccountingPeriod;
use Headless\Accounting\Models\FiscalYear;
use Illuminate\Console\Command;

/**
 * Generates FiscalYear + AccountingPeriod rows for a given year range so
 * the journal has somewhere to land.
 */
final class InstallPeriodsCommand extends Command
{
    protected $signature = 'ha:install-periods {--year= : Starting year (default: current year)} {--span=2 : How many years to provision}';

    protected $description = 'Generate accounting fiscal years and monthly periods';

    public function handle(): int
    {
        $start = (int) ($this->option('year') ?? date('Y'));
        $span = max(1, (int) $this->option('span'));

        for ($y = $start; $y < $start + $span; $y++) {
            $fy = FiscalYear::query()->updateOrCreate(
                ['name' => (string) $y],
                ['starts_at' => "$y-01-01", 'ends_at' => "$y-12-31"],
            );

            $cursor = CarbonImmutable::create($y, 1, 1);
            for ($m = 1; $m <= 12; $m++) {
                AccountingPeriod::query()->updateOrCreate(
                    ['code' => sprintf('%d-%02d', $y, $m)],
                    [
                        'fiscal_year_id' => $fy->id,
                        'starts_at' => $cursor->startOfMonth()->toDateString(),
                        'ends_at' => $cursor->endOfMonth()->toDateString(),
                    ],
                );
                $cursor = $cursor->addMonth();
            }
        }
        $this->info("Provisioned periods for $span year(s) starting $start.");

        return self::SUCCESS;
    }
}
