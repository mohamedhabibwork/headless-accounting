<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Banking\BankAccount;

/**
 * Bankable — host-side contract for any model that can be plugged
 * into the package's payment side as the originator of an outgoing
 * payment or a destination of an incoming payment. The package's
 * built-in {@see BankAccount} already
 * implements this contract, but hosts sometimes manage bank accounts
 * in a separate banking service.
 *
 * The package only ever reads the account via:
 *   - IBAN/BIC
 *   - currency
 *   - a default-flag
 *
 * Implementations should expose those as plain accessors.
 */
interface Bankable
{
    public function iban(): ?string;

    public function bic(): ?string;

    public function currency(): string;

    /** Whether this account should be picked up automatically. */
    public function isDefault(): bool;
}
