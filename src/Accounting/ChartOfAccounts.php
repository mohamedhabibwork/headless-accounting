<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Headless\Accounting\Models\Account;

/**
 * ChartOfAccounts — installs the default chart and exposes accessors for
 * semantic account codes.
 *
 * Replace by binding your own implementation in config:
 *   'accounting.chart_of_accounts' => \App\Accounting\MyChartOfAccounts::class,
 */
interface ChartOfAccounts
{
    /** Called once during install. Idempotent. */
    public function install(): void;

    /** @return array<string,int> map of semantic code → account id */
    public function map(): array;
}
