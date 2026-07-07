<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Headless\Accounting\Models\Account;

/**
 * DefaultChartOfAccounts — installs a sensible default chart (EN/FR/US
 * friendly). Overwrite via config if you have your own.
 */
final class DefaultChartOfAccounts implements ChartOfAccounts
{
    /** @return array<string, array{name:string,type:string,currency:?string}> */
    public static function blueprint(): array
    {
        return [
            // Assets
            '1000' => ['name' => 'Cash',                              'type' => Account::TYPE_ASSET,     'currency' => null],
            '1100' => ['name' => 'Bank — Operating',                  'type' => Account::TYPE_ASSET,     'currency' => null],
            '1200' => ['name' => 'Accounts Receivable',               'type' => Account::TYPE_ASSET,     'currency' => null],
            '1400' => ['name' => 'Inventory',                         'type' => Account::TYPE_ASSET,     'currency' => null],
            '1410' => ['name' => 'Inventory — Raw Materials',         'type' => Account::TYPE_ASSET,     'currency' => null],
            '1420' => ['name' => 'Inventory — Work In Progress',      'type' => Account::TYPE_ASSET,     'currency' => null],
            '1430' => ['name' => 'Inventory — Finished Goods',        'type' => Account::TYPE_ASSET,     'currency' => null],
            '1440' => ['name' => 'Inventory — In Transit (GRNI)',     'type' => Account::TYPE_ASSET,     'currency' => null],
            '1450' => ['name' => 'Inventory — Consignment',           'type' => Account::TYPE_ASSET,     'currency' => null],
            '1500' => ['name' => 'Stripe / PayPal Clearing',          'type' => Account::TYPE_ASSET,     'currency' => null],
            // Liabilities
            '2000' => ['name' => 'Accounts Payable',                  'type' => Account::TYPE_LIABILITY, 'currency' => null],
            '2010' => ['name' => 'Goods Received Not Invoiced (GRNI)', 'type' => Account::TYPE_LIABILITY, 'currency' => null],
            '2020' => ['name' => 'Consignment Payable',               'type' => Account::TYPE_LIABILITY, 'currency' => null],
            '2100' => ['name' => 'VAT Payable (Output)',              'type' => Account::TYPE_LIABILITY, 'currency' => null],
            '2200' => ['name' => 'Tax Payable (Sales)',               'type' => Account::TYPE_LIABILITY, 'currency' => null],
            '2300' => ['name' => 'Customer Prepayments',              'type' => Account::TYPE_LIABILITY, 'currency' => null],
            // Equity
            '3000' => ['name' => 'Owner Equity',                      'type' => Account::TYPE_EQUITY,    'currency' => null],
            '3100' => ['name' => 'Retained Earnings',                 'type' => Account::TYPE_EQUITY,    'currency' => null],
            // Revenue
            '4000' => ['name' => 'Sales Revenue',                     'type' => Account::TYPE_REVENUE,   'currency' => null],
            '4100' => ['name' => 'Sales Discounts (contra-revenue)', 'type' => Account::TYPE_REVENUE,   'currency' => null],
            '4200' => ['name' => 'Refunds (contra-revenue)',          'type' => Account::TYPE_REVENUE,   'currency' => null],
            '4300' => ['name' => 'Shipping Revenue',                  'type' => Account::TYPE_REVENUE,   'currency' => null],
            '4400' => ['name' => 'Inventory Gain / Overage',          'type' => Account::TYPE_REVENUE,   'currency' => null],
            // Expenses
            '5000' => ['name' => 'Cost of Goods Sold',                'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '5100' => ['name' => 'Inventory Loss / Shrinkage',        'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '5200' => ['name' => 'Inventory Damage Write-off',        'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '5300' => ['name' => 'Production Variance',               'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '5400' => ['name' => 'Inventory Revaluation',             'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '6000' => ['name' => 'Shipping Expense',                  'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '6100' => ['name' => 'Payment Processing Fees',           'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '7000' => ['name' => 'Marketing',                         'type' => Account::TYPE_EXPENSE,   'currency' => null],
            '9000' => ['name' => 'Bank Fees',                         'type' => Account::TYPE_EXPENSE,   'currency' => null],
        ];
    }

    public function install(): void
    {
        foreach (self::blueprint() as $code => $spec) {
            Account::query()->updateOrCreate(['code' => $code], $spec + ['active' => true]);
        }
    }

    public function map(): array
    {
        return Account::query()->pluck('id', 'code')->all();
    }
}
