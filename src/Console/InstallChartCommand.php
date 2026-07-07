<?php

declare(strict_types=1);

namespace Headless\Accounting\Console;

use Headless\Accounting\Accounting\ChartOfAccounts;
use Illuminate\Console\Command;

final class InstallChartCommand extends Command
{
    protected $signature = 'ha:install-chart {--force : Reinstall default accounts even if they exist}';

    protected $description = 'Install (or reset) the default chart of accounts';

    public function handle(ChartOfAccounts $chart): int
    {
        $this->info('Installing chart of accounts…');
        $chart->install();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
