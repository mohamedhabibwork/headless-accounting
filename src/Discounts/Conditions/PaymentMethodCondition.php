<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;

final class PaymentMethodCondition extends BaseCondition
{
    public function type(): string
    {
        return 'payment_method';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $methods = (array) $this->get('methods', []);
        if ($methods === []) {
            return true;
        }
        $intent = $ctx->extras['payment_method'] ?? null;

        return $intent ? in_array((string) $intent, $methods, true) : false;
    }
}
