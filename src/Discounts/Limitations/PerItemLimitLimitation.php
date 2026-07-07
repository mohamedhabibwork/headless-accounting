<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Limitations;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseLimitation;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;

final class PerItemLimitLimitation extends BaseLimitation
{
    public function type(): string
    {
        return 'per_item';
    }

    public function apply(EvaluationContext $ctx, DiscountApplication $application): DiscountApplication
    {
        $max = (int) $this->get('max', 1);
        if ($max <= 0) {
            return $application;
        }
        if (count($application->lines()) <= $max) {
            return $application;
        }

        $kept = array_slice($application->lines(), 0, $max);
        $newTotal = array_reduce($kept, fn ($carry, $l) => $carry + $l['amount']->amount, 0);

        $out = new DiscountApplication(
            discountId: $application->discountId,
            discountName: $application->discountName,
            total: new Money($newTotal, $application->total->currency),
            requested: $application->requested,
        );
        foreach ($kept as $l) {
            $out->addLine($l['amount'], $l['requested'], ['variant_id' => $l['variant_id'], 'product_id' => $l['product_id']], 'per_item_limit');
        }

        return $out;
    }
}
