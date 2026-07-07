<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;

final class MinItemQuantityCondition extends BaseCondition
{
    public function type(): string
    {
        return 'min_item_quantity';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $products = (array) $this->get('products', []);
        $minTotal = (int) $this->get('quantity', 0);

        $count = 0;
        foreach ($ctx->items as $i) {
            if (! $i instanceof OrderItem) {
                continue;
            }
            if ($products === [] || in_array((int) $i->variant?->product_id, $products, true)) {
                $count += (int) $i->quantity;
            }
        }

        return $count >= $minTotal;
    }
}
