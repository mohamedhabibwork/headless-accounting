<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Payment;

use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Invoice;

/**
 * CheckCreditLimit — guards order placement / invoice issuance.
 * Throws when an order/customer would exceed their allowed credit.
 */
class CheckCreditLimit
{
    public function execute(Customer $customer, Invoice|int $amountMinor, ?string $currency = null): bool
    {
        $customer->refresh();

        $creditLimit = (int) $customer->credit_limit_minor;
        if ($creditLimit <= 0) {
            return true;
        }

        $amount = $amountMinor instanceof Invoice ? (int) $amountMinor->grand_total_minor : (int) $amountMinor;

        // Sum up AR for this customer: open invoices + pending debit notes.
        $openAr = (int) $customer->invoices()
            ->whereIn('state', [Invoice::STATE_ISSUED, Invoice::STATE_PARTIAL])
            ->sum('balance_minor');

        if ($openAr + $amount > $creditLimit) {
            throw new AccountingException(sprintf(
                'Credit limit exceeded for customer %s: %.2f (open %.2f + new %.2f) > %.2f',
                $customer->email, ($openAr + $amount) / 100, $openAr / 100, $amount / 100, $creditLimit / 100,
            ));
        }

        return true;
    }
}
