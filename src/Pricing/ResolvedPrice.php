<?php

declare(strict_types=1);

namespace Headless\Accounting\Pricing;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Support\Config;

/**
 * ResolvedPrice — the result of a price resolution. Includes the
 * final Money, optional compareAt (strike-through), currency, locale,
 * tax-inclusivity flag and a list of price-list IDs used to resolve it
 * (for audit).
 */
final class ResolvedPrice
{
    public readonly string $locale;

    public function __construct(
        public readonly Money $amount,
        public readonly ?Money $compareAt = null,
        public readonly bool $taxInclusive = false,
        public readonly array $appliedPriceListIds = [],
        ?string $locale = null,
    ) {
        $this->locale = $locale ?? Config::string('headless-accounting.locale.default');
    }

    public function isOnSale(): bool
    {
        return $this->compareAt !== null && $this->compareAt->amount > $this->amount->amount;
    }

    public function localized(string $locale): string
    {
        return $this->amount->format($locale);
    }
}
