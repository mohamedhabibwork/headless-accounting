<?php

declare(strict_types=1);

namespace Headless\Accounting\Loans;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\Loan;
use Headless\Accounting\Models\LoanInstallment;

/**
 * AmortizationSchedule — generates a fixed-principal loan installment
 * schedule (annuity formula). Persists rows into LoanInstallment.
 */
class AmortizationSchedule
{
    public function generate(Loan $loan): array
    {
        $loan->refresh();
        $monthlyRate = (float) $loan->interest_rate_pct / 100 / 12;
        $numPayments = $loan->term_months;
        if ($numPayments <= 0) {
            return [];
        }

        // Annuity formula
        $p = (float) $loan->principal_minor;
        if ($monthlyRate == 0) {
            $payment = (int) round($p / $numPayments);
        } else {
            $annuity = $p * ($monthlyRate * pow(1 + $monthlyRate, $numPayments)) / (pow(1 + $monthlyRate, $numPayments) - 1);
            $payment = (int) round($annuity);
        }

        $rows = [];
        $balance = (int) $loan->principal_minor;
        $cursor = CarbonImmutable::parse($loan->start_date);

        for ($i = 1; $i <= $numPayments; $i++) {
            $interest = (int) round($balance * $monthlyRate);
            $principal = max(0, $payment - $interest);
            $balance = max(0, $balance - $principal);

            $rows[] = [
                'loan_id' => $loan->id,
                'installment_no' => $i,
                'due_date' => $cursor->toDateString(),
                'currency' => $loan->currency,
                'principal_minor' => $principal,
                'interest_minor' => $interest,
                'total_minor' => $payment,
                'paid_minor' => 0,
                'state' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ];

            $cursor = $cursor->addMonth();
        }

        LoanInstallment::query()->where('loan_id', $loan->id)->delete();
        foreach ($rows as $row) {
            LoanInstallment::create($row);
        }

        return $rows;
    }
}
