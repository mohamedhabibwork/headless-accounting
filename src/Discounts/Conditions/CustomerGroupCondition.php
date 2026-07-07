<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;

final class CustomerGroupCondition extends BaseCondition
{
    public function type(): string
    {
        return 'customer_group';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $groups = (array) $this->get('groups', []);
        if ($groups === []) {
            return true;
        }
        if (! $ctx->customer) {
            return false;
        }

        $customerGroups = (array) $ctx->customer->groups->pluck('code')->all();
        foreach ($groups as $g) {
            if (in_array((string) $g, $customerGroups, true)) {
                return true;
            }
        }

        return false;
    }
}
