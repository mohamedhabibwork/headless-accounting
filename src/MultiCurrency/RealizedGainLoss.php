<?php

declare(strict_types=1);

namespace Headless\Accounting\MultiCurrency;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Models\Currency;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\RealizedGainLoss;
use Headless\Accounting\Support\RoundingMode;

/**
 * RealizedGainLoss — captures the FX gain/loss when a foreign-currency
 * receivable or payable is settled.
 *
 * Triggered by the {@see \\Headless\\Accounting\\Actions\\Payment\\CapturePayment}
 * action when the payment currency ≠ payable currency.
 */
class RealizedGainLoss
{
    public function __construct(private readonly Journal $journal) {}

    public function record(
        Payment $payment,
        float $fxRateAtBooking,
        float $fxRateAtPayment,
        string $currency,
    ): RealizedGainLoss {
        $delta = ($fxRateAtPayment - $fxRateAtBooking) * ($payment->amount_minor / 100);
        $amountMinor = (int) RoundingMode::roundWith($delta * 100);

        $entry = null;
        if ($amountMinor !== 0) {
            $entry = $this->journal->post(
                source: $payment, currency: $currency,
                description: 'Realized FX gain/loss on payment '.$payment->number,
                autoPosted: true,
                postings: [
                    // Sign by gain/loss: positive = gain (credit), negative = loss (debit)
                    ['account' => '7000', ($amountMinor > 0 ? 'credit' : 'debit') => abs($amountMinor), 'memo' => 'FX gain/loss'],
                    ['account' => '1200', ($amountMinor > 0 ? 'debit' : 'credit') => abs($amountMinor), 'memo' => 'AR adjustment'],
                ],
            );
        }

        return RealizedGainLoss::create([
            'company_id' => $payment->company_id,
            'payment_id' => $payment->id,
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'fx_rate_at_booking' => $fxRateAtBooking,
            'fx_rate_at_payment' => $fxRateAtPayment,
            'journal_entry_id' => $entry?->id,
        ]);
    }
}
