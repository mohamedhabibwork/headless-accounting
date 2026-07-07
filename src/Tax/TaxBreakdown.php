<?php

declare(strict_types=1);

namespace Headless\Accounting\Tax;

use Headless\Accounting\Currency\Money;

/**
 * TaxBreakdown — value object returned by the TaxEngine. It contains
 * per-line tax amounts and convenience totals.
 */
final class TaxBreakdown
{
    /** @var TaxLine[] */
    private array $lines = [];

    public function __construct(
        public readonly string $currency,
        public readonly Money $subtotal,
        public readonly bool $inclusive,
    ) {}

    public function add(TaxLine $line): void
    {
        $this->lines[] = $line;
    }

    /** @return TaxLine[] */
    public function lines(): array
    {
        return $this->lines;
    }

    public function total(): Money
    {
        $cents = 0;
        foreach ($this->lines as $l) {
            $cents += $l->amount->amount;
        }

        return new Money($cents, $this->currency);
    }

    /** Gross = subtotal + tax (exclusive) OR subtotal as is (inclusive). */
    public function gross(): Money
    {
        if ($this->inclusive) {
            return $this->subtotal;
        }

        return new Money($this->subtotal->amount + $this->total()->amount, $this->currency);
    }
}
