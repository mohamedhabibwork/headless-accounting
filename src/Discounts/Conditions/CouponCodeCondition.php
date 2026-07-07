<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;

final class CouponCodeCondition extends BaseCondition
{
    public function type(): string
    {
        return 'coupon_code';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $coupons = array_map('strtoupper', (array) $this->get('codes', []));
        if ($coupons === []) {
            return true;
        }
        $provided = strtoupper((string) $ctx->coupon());

        return $provided !== '' && in_array($provided, $coupons, true);
    }
}
