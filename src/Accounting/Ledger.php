<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Posting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ledger — convenience helpers over {@see Posting} for building
 * financial statements.
 *
 * All aggregates are computed with a single grouped query so we never
 * fan out into N+1 queries over accounts / postings.
 */
final class Ledger
{
    public function trialBalance(string $currency, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now();

        $rows = DB::table((new Account)->getTable().' as a')
            ->leftJoin(
                (new Posting)->getTable().' as p',
                fn ($join) => $join->on('p.account_id', '=', 'a.id')->where('p.currency', '=', $currency),
            )
            ->where('a.active', true)
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->selectRaw('a.code, a.name, a.type, COALESCE(SUM(p.debit_minor), 0) AS total_debit, COALESCE(SUM(p.credit_minor), 0) AS total_credit')
            ->get();

        return $rows->map(fn ($row) => [
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'balance' => $this->signedBalance((string) $row->type, (int) $row->total_debit, (int) $row->total_credit),
        ])->all();
    }

    public function incomeStatement(string $currency, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byType = DB::table((new Posting)->getTable().' as p')
            ->join((new Account)->getTable().' as a', 'a.id', '=', 'p.account_id')
            ->whereIn('a.type', [Account::TYPE_REVENUE, Account::TYPE_EXPENSE])
            ->where('p.currency', $currency)
            ->whereBetween('p.created_at', [$from, $to])
            ->groupBy('a.type')
            ->selectRaw('a.type, COALESCE(SUM(p.debit_minor), 0) AS total_debit, COALESCE(SUM(p.credit_minor), 0) AS total_credit')
            ->get()
            ->keyBy('type');

        $revenue = $this->typeBalance($byType, Account::TYPE_REVENUE);
        $expense = $this->typeBalance($byType, Account::TYPE_EXPENSE);

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'net' => $revenue - $expense,
        ];
    }

    /**
     * Sign a balance row by account type — debit-normal for asset/expense,
     * credit-normal for the rest. Mirrors {@see Account::balance()}.
     */
    private function signedBalance(string $type, int $debit, int $credit): int
    {
        return match ($type) {
            Account::TYPE_ASSET, Account::TYPE_EXPENSE => $debit - $credit,
            default => $credit - $debit,
        };
    }

    private function typeBalance(Collection $byType, string $type): int
    {
        $row = $byType->get($type);

        if ($row === null) {
            return 0;
        }

        return $this->signedBalance($type, (int) $row->total_debit, (int) $row->total_credit);
    }
}
