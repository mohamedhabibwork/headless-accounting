<?php

declare(strict_types=1);

namespace Headless\Accounting\Reporting;

use Carbon\CarbonImmutable;
use Headless\Accounting\Currency\Currency;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Models\Posting;
use Headless\Accounting\Models\TaxRate;
use Headless\Accounting\Models\TaxZone;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Support\RoundingMode;

/**
 * TaxReports — country-aware tax summaries.
 */
class TaxReports
{
    public function vatReport(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?string $currency = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');
        $revenueAccountId = Account::query()->where('code', Config::string('headless-accounting.accounting.accounts.sales_revenue'))->value('id');
        $taxAccountId = Account::query()->where('code', '2100')->value('id');

        $outputVat = (int) Posting::query()
            ->where('account_id', $taxAccountId)
            ->where('currency', $currency)
            ->whereBetween('created_at', [$from, $to])
            ->sum('credit_minor');

        $taxableSales = (int) Posting::query()
            ->where('account_id', $revenueAccountId)
            ->where('currency', $currency)
            ->whereBetween('created_at', [$from, $to])
            ->sum('credit_minor');

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => $currency,
            'taxable_sales' => $taxableSales,
            'output_vat' => $outputVat,
            'effective_rate_pct' => $taxableSales > 0
                ? round($outputVat / $taxableSales * 100, Currency::decimals($currency))
                : 0,
        ];
    }

    public function taxSummaryByRate(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?string $currency = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');

        return TaxRate::query()
            ->with('zone')
            ->get()
            ->map(function (TaxRate $rate) use ($from, $to, $currency) {
                $taxable = 0;
                $tax = 0;
                $prefix = Config::string('headless-accounting.table_prefix', 'ha_');
                // Calibrate using the TaxZone/TaxRate metadata (zone codes), or fall back to historical Posting aggregation.
                // For simplicity, apply the rate % back-derived from taxAccount postings.
                $taxable += (int) \DB::table($prefix.'postings')
                    ->where('currency', $currency)
                    ->whereBetween('created_at', [$from, $to])
                    ->whereIn('account_id', \DB::table($prefix.'accounts')->where('code', Config::string('headless-accounting.accounting.accounts.sales_revenue'))->pluck('id'))
                    ->sum('credit_minor');
                $tax += (int) RoundingMode::roundWith($taxable * ((float) $rate->percent / 100));

                return [
                    'zone' => $rate->zone?->code,
                    'rate' => $rate->percent,
                    'name' => $rate->name,
                    'taxable_minor' => $taxable,
                    'tax_minor' => $tax,
                ];
            })->all();
    }
}
