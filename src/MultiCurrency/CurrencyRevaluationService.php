<?php

declare(strict_types=1);

namespace Headless\Accounting\MultiCurrency;

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Currency\CurrencyConverter;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\CurrencyRevaluation;
use Headless\Accounting\Models\Posting;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Support\RoundingMode;

/**
 * CurrencyRevaluationService — runs a period-end FX revaluation on
 * open foreign-currency account balances.
 *
 *   $result = (new CurrencyRevaluationService)->execute($companyId, 'USD', $endOfPeriod);
 */
class CurrencyRevaluationService
{
    public function __construct(
        private readonly CurrencyConverter $converter,
        private readonly Journal $journal,
    ) {}

    public function execute(int $companyId, string $currency, CarbonImmutable $asOf): CurrencyRevaluation
    {
        $rows = [];
        $balanceByAccount = [];

        // Walk Postings for monetary accounts in the chosen foreign currency.
        $accounts = Account::query()->whereIn('type', [Account::TYPE_ASSET, Account::TYPE_LIABILITY])->get();

        foreach ($accounts as $acc) {
            $foreignBalance = (int) Posting::query()
                ->where('account_id', $acc->id)
                ->where('currency', $currency)
                ->where('created_at', '<=', $asOf)
                ->sum(\DB::raw('debit_minor - credit_minor'));

            if ($foreignBalance == 0) {
                continue;
            }

            $rate = $this->converter->rate($currency, Config::string('headless-accounting.currency.default'), $asOf);
            $eurValue = (int) RoundingMode::roundWith($foreignBalance * $rate);
            $balanceByAccount[$acc->id] = [
                'account' => $acc,
                'foreign_minor' => $foreignBalance,
                'eur_minor' => $eurValue,
            ];
        }

        // Emit one balanced journal entry summarizing all deltas:
        //   Dr FX revaluation loss / Cr FX revaluation gain (net)
        $totalNetDelta = 0;
        $postings = [];
        foreach ($balanceByAccount as $row) {
            $eur = $row['eur_minor'];
            $accountId = $row['account']->id;
            if ($eur === 0) {
                continue;
            }
            $postings[] = [
                'account_id' => $accountId,
                ($row['account']->type === Account::TYPE_ASSET ? 'debit' : 'credit') => abs($eur),
                'memo' => "Revaluation of {$currency} ({$row['foreign_minor']})",
            ];
            $totalNetDelta += $eur;
        }

        $summary = [['account' => '7000', ($totalNetDelta > 0 ? 'debit' : 'credit') => abs($totalNetDelta), 'memo' => 'FX revaluation delta']];

        $entry = null;
        if (! empty($postings)) {
            $entry = $this->journal->post(
                source: $account = $accounts->first() ?? Account::query()->first(),
                currency: Config::string('headless-accounting.currency.default'),
                description: 'FX revaluation '.$currency.' as of '.$asOf->toDateString(),
                autoPosted: false,
                postings: array_merge($postings, $summary),
            );
        }

        return CurrencyRevaluation::updateOrCreate(
            ['company_id' => $companyId, 'currency' => $currency, 'as_of' => $asOf->toDateString()],
            [
                'breakdown' => $balanceByAccount,
                'journal_entry_id' => $entry?->id,
            ],
        );
    }
}
