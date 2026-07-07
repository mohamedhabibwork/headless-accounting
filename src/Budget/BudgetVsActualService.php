<?php

declare(strict_types=1);

namespace Headless\Accounting\Budget;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Budget;
use Headless\Accounting\Models\BudgetLine;
use Headless\Accounting\Models\Posting;

/**
 * BudgetVsActualService — keeps BudgetLine.actual_minor up to date by
 * rolling Posting totals per Account/Period. Can be re-run as often as
 * desired (idempotent: replaces actual_minor each run).
 *
 *   $service = (new BudgetVsActualService);
 *   $service->refreshFor($budget, $year);
 *
 *   $rows = $service->report($budget);
 *   // [['account_code' => 4000, 'planned' => 5000, 'actual' => 4200, 'variance_pct' => -16, ...]]
 */
class BudgetVsActualService
{
    public function refreshFor(Budget $budget, int $year): int
    {
        $currency = $budget->currency;
        $rows = BudgetLine::query()
            ->where('budget_id', $budget->id)
            ->get();

        $count = 0;
        foreach ($rows as $line) {
            $monthFilter = $line->month
                ? [CarbonImmutable::create($year, $line->month, 1)]
                : [CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year, 12, 31)->endOfMonth()];

            $actual = 0;
            foreach ($monthFilter as $start) {
                $end = $start->endOfMonth();
                $actual += (int) Posting::query()
                    ->where('account_id', $line->account_id)
                    ->where('currency', $currency)
                    ->whereBetween('created_at', [$start->toDateString(), $end->toDateString()])
                    ->selectRaw('SUM(debit_minor) - SUM(credit_minor) AS bal')
                    ->value('bal');
            }
            $line->actual_minor = abs($actual);
            $line->variance_pct = $line->planned_minor > 0
                ? round((($line->actual_minor - $line->planned_minor) / $line->planned_minor) * 100, 4)
                : 0;
            $line->save();
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function report(Budget $budget): array
    {
        return BudgetLine::query()
            ->where('budget_id', $budget->id)
            ->with('account')
            ->chunkMap(100, fn (BudgetLine $line) => [
                'account_code' => $line->account->code,
                'account_name' => $line->account->name,
                'month' => $line->month,
                'planned_minor' => (int) $line->planned_minor,
                'actual_minor' => (int) $line->actual_minor,
                'variance_minor' => (int) $line->actual_minor - (int) $line->planned_minor,
                'variance_pct' => (float) $line->variance_pct,
            ])
            ->all();
    }
}
