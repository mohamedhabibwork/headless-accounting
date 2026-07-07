<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;

final class CategoryInCondition extends BaseCondition
{
    public function type(): string
    {
        return 'category_in';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $ids = (array) $this->get('categories', []);
        if ($ids === []) {
            return true;
        }

        foreach ($ctx->items as $i) {
            if (! $i instanceof OrderItem) {
                continue;
            }
            foreach ((array) ($i->variant?->product?->categories ?? []) as $cat) {
                if (in_array((int) $cat->id, $ids, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
