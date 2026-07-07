<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Limitations;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseLimitation;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\DiscountUsage;

final class TotalUsageLimitLimitation extends BaseLimitation
{
    public function type(): string
    {
        return 'total_usage';
    }

    public function apply(EvaluationContext $ctx, DiscountApplication $application): DiscountApplication
    {
        $max = (int) $this->get('max', 0);
        if ($max <= 0) {
            return $application;
        }

        $used = DiscountUsage::query()
            ->where('discount_id', $application->discountId)
            ->count();

        if ($used >= $max) {
            return new DiscountApplication(
                discountId: $application->discountId,
                discountName: $application->discountName,
                total: new Money(0, $application->total->currency),
                requested: $application->requested,
            );
        }

        return $application;
    }
}
