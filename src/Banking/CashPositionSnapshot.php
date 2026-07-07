<?php

declare(strict_types=1);

namespace Headless\Accounting\Banking;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Posting;

/**
 * CashPositionSnapshot — calculates the company's cash position
 * (bank + cash accounts) at a given date in a chosen currency, optionally
 * consolidating through the currency converter for multi-currency reports.
 */
class CashPositionSnapshot
{
    /**
     * @return array{
     *   as_of: string,
     *   currency: string,
     *   snapshot: array<string, int>,
     *   total_minor: int,
     * }
     */
    public function execute(int $companyId, string $currency, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::today();

        // Cash & bank GL accounts (1000-1999 in our default chart).
        $bankAccountIds = Account::query()
            ->whereIn('code', ['1000', '1100', '1500'])
            ->pluck('id');

        $balances = [];
        foreach ($bankAccountIds as $accId) {
            $acc = Account::find($accId);
            $balance = (int) Posting::query()
                ->where('account_id', $accId)
                ->where('currency', $currency)
                ->where('created_at', '<=', $asOf)
                ->sum(\DB::raw('debit_minor - credit_minor'));
            $balances[$acc->name] = $balance;
        }

        $total = array_sum($balances);

        // Persist snapshot.
        CashPosition::updateOrCreate(
            ['company_id' => $companyId, 'as_of' => $asOf->toDateString(), 'currency' => $currency],
            ['snapshot' => $balances + ['total' => $total]],
        );

        return [
            'as_of' => $asOf->toDateString(),
            'currency' => $currency,
            'snapshot' => $balances,
            'total_minor' => $total,
        ];
    }
}
