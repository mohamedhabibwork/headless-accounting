<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Payment;

use Headless\Accounting\Contracts\Payable;
use Headless\Accounting\Models\Bill;
use Headless\Accounting\Models\Invoice;
use Headless\Accounting\Models\Payment;
use Headless\Accounting\Models\PaymentAllocation;

/**
 * AllocatePayment — links a captured {@see Payment} to one or more
 * open receivables/payables (Invoice, Bill, CreditNote, DebitNote).
 *
 *   $allocations = (new AllocatePayment)->execute(
 *       payment: $payment,
 *       targets: [
 *           ['target' => $invoice1, 'amount' => 1000_00],
 *           ['target' => $invoice2, 'amount' => 500_00],
 *       ],
 *   );
 */
class AllocatePayment
{
    /**
     * @param  array<int, array{target: Payable, amount: int}>  $targets
     * @return PaymentAllocation[]
     */
    public function execute(Payment $payment, array $targets, $companyId = null): array
    {
        $remaining = $payment->amount_minor;
        $allocations = [];

        foreach ($targets as $entry) {
            $target = $entry['target'];
            $amount = (int) $entry['amount'];

            if (! $target instanceof Payable) {
                throw new \InvalidArgumentException('Target must implement Payable.');
            }
            if ($amount > $remaining) {
                throw new \InvalidArgumentException(
                    "Allocated amount {$amount} exceeds remaining payment {$remaining}."
                );
            }

            $allocations[] = PaymentAllocation::create([
                'company_id' => $companyId ?? $payment->company_id,
                'payment_id' => $payment->id,
                'target_type' => $target->getMorphClass(),
                'target_id' => $target->getKey(),
                'currency' => $target->currency(),
                'amount_minor' => $amount,
                'fx_rate' => 1.0,
                'allocated_at' => now(),
            ]);

            // Update target balances.
            $target->paid_minor = (int) $target->totalPaid() + $amount;
            $target->balance_minor = max(0, $target->totalDue() - $amount);

            if ($target instanceof Invoice) {
                $target->state = $target->balance_minor <= 0
                    ? Invoice::STATE_PAID
                    : Invoice::STATE_PARTIAL;
                $target->save();
            } elseif ($target instanceof Bill) {
                $target->paid_minor = (int) $target->totalPaid() + $amount;
                $target->balance_minor = max(0, $target->totalDue() - $amount);
                $target->state = $target->balance_minor <= 0
                    ? Bill::STATE_PAID
                    : Bill::STATE_PARTIAL;
                $target->save();
            }

            $remaining -= $amount;
        }

        return $allocations;
    }
}
