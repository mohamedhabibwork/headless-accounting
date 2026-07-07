<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Limitations;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseLimitation;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;

/**
 * Caps the absolute total discount to a specific amount and currency.
 *
 * Note: this is a "soft" cap meant for percentage discounts. The
 * PercentageDiscount driver also exposes its own maximum_discount_amount;
 * use whichever fits your UX.
 */
final class MaxDiscountAmountLimitation extends BaseLimitation
{
    public function type(): string
    {
        return 'max_amount';
    }

    public function apply(EvaluationContext $ctx, DiscountApplication $application): DiscountApplication
    {
        $cap = (int) $this->get('amount', 0);
        if ($cap <= 0) {
            return $application;
        }
        if ($application->total->amount <= $cap) {
            return $application;
        }

        return new DiscountApplication(
            discountId: $application->discountId,
            discountName: $application->discountName,
            total: new Money($cap, $application->total->currency),
            requested: $application->requested,
        );
    }
}
