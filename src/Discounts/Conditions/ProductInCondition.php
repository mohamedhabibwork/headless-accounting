<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;

final class ProductInCondition extends BaseCondition
{
    public function type(): string
    {
        return 'product_in';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $ids = (array) $this->get('products', []);
        if ($ids === []) {
            return true;
        }

        foreach ($ctx->items as $i) {
            if (! $i instanceof OrderItem) {
                continue;
            }
            if (in_array((int) $i->variant?->product_id, $ids, true)) {
                return true;
            }
        }

        return false;
    }
}
