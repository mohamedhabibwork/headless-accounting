<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Limitations;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseLimitation;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\DiscountUsage;

/**
 * Clips the discount to a maximum number of *applications* per order.
 * Each Buy-X-Get-Y "cycle" counts as one application; for percentage/fixed
 * discounts this normally means 1 application.
 */
final class MaxApplicationsPerOrderLimitation extends BaseLimitation
{
    public function type(): string
    {
        return 'max_per_order';
    }

    public function apply(EvaluationContext $ctx, DiscountApplication $application): DiscountApplication
    {
        $max = (int) $this->get('max', 0);
        if ($max <= 0) {
            return $application;
        }

        $orderId = $ctx->order?->getKey();
        if (! $orderId) {
            return $application;
        }

        $used = DiscountUsage::query()
            ->where('source_type', $ctx->order->getMorphClass())
            ->where('source_id', $orderId)
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
