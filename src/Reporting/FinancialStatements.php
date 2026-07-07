<?php

declare(strict_types=1);

namespace Headless\Accounting\Reporting;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Posting;

/**
 * FinancialStatements — Balance Sheet, Income Statement, and Cash Flow.
 *
 *   $bs = (new FinancialStatements)->balanceSheet($companyId, 'EUR', $asOf);
 *   $pl = (new FinancialStatements)->incomeStatement($companyId, 'EUR', $from, $to);
 *   $cf = (new FinancialStatements)->cashFlow($companyId, 'EUR', $from, $to);
 */
class FinancialStatements
{
    public function balanceSheet(int $companyId, string $currency, CarbonImmutable $asOf): array
    {
        $balances = $this->balancesAt($companyId, $currency, $asOf);

        return [
            'as_of' => $asOf->toDateString(),
            'currency' => $currency,
            'assets' => $this->sumByType($balances, [Account::TYPE_ASSET]),
            'liabilities' => $this->sumByType($balances, [Account::TYPE_LIABILITY]),
            'equity' => $this->sumByType($balances, [Account::TYPE_EQUITY]),
            'liabilities_plus_equity' => 0,
            'lines' => $this->linesByType($balances),
        ];
    }

    public function incomeStatement(int $companyId, string $currency, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $balances = $this->balancesBetween($companyId, $currency, $from, $to);
        $revenue = $this->sumByType($balances, [Account::TYPE_REVENUE]);
        $expense = $this->sumByType($balances, [Account::TYPE_EXPENSE]);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => $currency,
            'revenue' => $revenue,
            'expense' => $expense,
            'net' => $revenue - $expense,
            'lines' => $this->linesByType($balances),
        ];
    }

    public function cashFlow(int $companyId, string $currency, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bs = $this->balancesBetween($companyId, $currency, $from, $to);
        $ops = collect($bs)->filter(fn ($r) => in_array($r['account']->type, [Account::TYPE_REVENUE, Account::TYPE_EXPENSE]));
        $netIncome = $ops->reduce(fn ($carry, $r) => $carry + (
            $r['account']->type === Account::TYPE_REVENUE
                ? ($r['credit'] - $r['debit'])
                : ($r['debit'] - $r['credit'])
        ), 0);

        $arChange = (int) Posting::query()
            ->where('account_id', Account::query()->where('code', '1200')->value('id'))
            ->where('currency', $currency)
            ->whereBetween('created_at', [$from, $to])
            ->sum(\DB::raw('debit_minor - credit_minor'));

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => $currency,
            'net_income' => $netIncome,
            'working_capital_change' => -$arChange,
            'net_cash' => $netIncome - $arChange,
        ];
    }

    /** @return array{account: Account, debit:int, credit:int, balance:int}[] */
    private function balancesAt(int $companyId, string $currency, CarbonImmutable $asOf): array
    {
        $rows = Account::query()->get();

        return $this->hydrateRows($rows, $companyId, $currency, $asOf, null);
    }

    /** @return array{account: Account, debit:int, credit:int, balance:int}[] */
    private function balancesBetween(int $companyId, string $currency, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Account::query()->get();

        return $this->hydrateRows($rows, $companyId, $currency, null, [$from, $to]);
    }

    /** @return array{account: Account, debit:int, credit:int, balance:int}[] */
    private function hydrateRows($rows, int $companyId, string $currency, ?CarbonImmutable $asOf, ?array $range): array
    {
        $out = [];
        foreach ($rows as $acc) {
            $q = Posting::query()
                ->where('account_id', $acc->id)
                ->where('currency', $currency);
            if ($asOf) {
                $q->where('created_at', '<=', $asOf);
            }
            if ($range) {
                $q->whereBetween('created_at', [$range[0], $range[1]]);
            }
            $debit = (int) $q->sum('debit_minor');
            $credit = (int) $q->sum('credit_minor');
            $balance = in_array($acc->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE])
                ? $debit - $credit
                : $credit - $debit;
            $out[] = compact('acc', 'debit', 'credit', 'balance');
        }

        return $out;
    }

    private function sumByType(array $rows, array $types): int
    {
        return (int) collect($rows)
            ->filter(fn ($r) => in_array($r['acc']->type, $types))
            ->sum('balance');
    }

    private function linesByType(array $rows): array
    {
        return collect($rows)->map(fn ($r) => [
            'code' => $r['acc']->code, 'name' => $r['acc']->name,
            'type' => $r['acc']->type, 'balance' => $r['balance'],
        ])->values()->all();
    }
}
