<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Support\Config;

final class MinOrderAmountCondition extends BaseCondition
{
    public function type(): string
    {
        return 'min_order_amount';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $min = (int) $this->get('amount', 0);
        $currency = (string) $this->get('currency', Config::get('headless-accounting.currency.default'));
        $subtotal = 0;
        foreach ($ctx->items as $i) {
            if ($i instanceof OrderItem) {
                $subtotal += ((int) $i->unit_price_minor) * ((int) $i->quantity);
            }
        }
        $money = new Money($subtotal, $currency);

        return $money->amount >= $min;
    }
}
