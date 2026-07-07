<?php

declare(strict_types=1);

namespace Headless\Accounting\HR;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Support\RoundingMode;

/**
 * PayrollCalculator — given a PayrollPeriod, runs across every active
 * employee, computes each component line, sums into a PayrollRun and
 * posts the matching journal entry:
 *
 *   Dr Salary expense (gross)
 *   Cr Bank (net paid)
 *   Cr Payable — Tax Withholding
 *   Cr Payable — Social Insurance
 */
class PayrollCalculator
{
    public function __construct(private readonly Journal $journal) {}

    public function run(PayrollPeriod $period): PayrollRun
    {
        $gross = 0;
        $taxes = 0;
        $social = 0;
        $net = 0;
        $employees = Employee::query()
            ->where('company_id', $period->company_id)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $period->ends_at);
            })
            ->with('components')
            ->get();

        $stateRaw = [
            'company_id' => $period->company_id,
            'period_id' => $period->id,
            'run_at' => now(),
            'currency' => Config::string('headless-accounting.currency.default'),
            'state' => 'computed',
        ];

        $run = PayrollRun::create($stateRaw);

        foreach ($employees as $employee) {
            $componentLines = [];
            $empGross = 0;
            $empTaxes = 0;
            $empSocial = 0;

            foreach ($employee->components as $component) {
                $amount = $this->compute($component, $employee);
                $componentLines[] = [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'component_name' => $component->name,
                    'type' => $component->type,
                    'amount_minor' => (int) $amount,
                    'currency' => $component->currency,
                ];

                if ($component->type === 'earning') {
                    $empGross += (int) $amount;
                } else {
                    $empTaxes += (int) $amount;
                }
            }

            PayrollLine::insert($componentLines);
            $empNet = $empGross - $empTaxes - $empSocial;

            $gross += $empGross;
            $taxes += $empTaxes;
            $social += $empSocial;
            $net += $empNet;
        }

        $run->update([
            'gross_minor' => $gross,
            'taxes_minor' => $taxes,
            'social_insurance_minor' => $social,
            'net_minor' => $net,
        ]);

        // Post journal
        $entry = $this->journal->post(
            source: $run,
            currency: $run->currency,
            description: 'Payroll for '.$period->name,
            autoPosted: true,
            postings: [
                ['account' => '7000', 'debit' => $gross,           'memo' => 'Salary expense'],
                ['account' => '2200', 'credit' => $taxes,           'memo' => 'Tax payable'],
                ['account' => '2200', 'credit' => $social,          'memo' => 'Social insurance payable'],
                ['account' => '1000', 'credit' => $net,             'memo' => 'Net pay'],
            ],
        );

        $run->update(['state' => 'posted', 'journal_entry_id' => $entry->id]);

        return $run->load('lines');
    }

    public function compute(SalaryComponent $component, Employee $employee): int
    {
        return match ($component->calc) {
            'fixed' => (int) RoundingMode::roundWith((float) $component->amount),
            'percent_of_basic' => (int) RoundingMode::roundWith(
                (float) $component->amount * (float) $employee->basic_salary_minor,
            ),
            'per_hour' => (int) RoundingMode::roundWith((float) $component->amount * (float) $employee->hours_per_week),
            default => 0,
        };
    }
}
