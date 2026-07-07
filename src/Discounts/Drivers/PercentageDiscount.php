<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Drivers;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseDiscountDriver;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Support\RoundingMode;
use InvalidArgumentException;

/**
 * PercentageDiscount — applies a flat percentage to the subtotal of
 * candidate lines. Optionally capped by `maximum_discount_amount` and
 * optionally split proportionally per line so the invoice breakdown
 * reads cleanly.
 *
 * Configuration:
 *   - percent                       (float, 0..100)
 *   - maximum_discount_amount       (int, minor units, optional)
 *   - distribute_per_line           (bool, optional, default true)
 */
final class PercentageDiscount extends BaseDiscountDriver
{
    public function type(): string
    {
        return 'percentage';
    }

    public function calculate(EvaluationContext $ctx, iterable $candidateLines): DiscountApplication
    {
        $percent = (float) $this->config('percent', 0);
        if ($percent <= 0 || $percent > 100) {
            throw new InvalidArgumentException('Percentage discount must be in (0, 100].');
        }

        $currency = $this->currency($ctx);
        $rounding = $this->roundingMode();
        $subtotal = $this->subtotal($candidateLines);
        $requested = (new Money((int) $subtotal, $currency))->percentage($percent, $rounding);
        $apply = $requested;

        $max = $this->config('maximum_discount_amount');
        if ($max !== null && $apply->amount > (int) $max) {
            $apply = new Money((int) $max, $currency);
        }

        $app = new DiscountApplication(
            discountId: $ctx->order?->id ?? 0,
            discountName: $this->config('__name', 'percentage'),
            total: $apply,
            requested: $requested,
        );

        if ($this->config('distribute_per_line', true)) {
            foreach ($candidateLines as $line) {
                if (! $line instanceof OrderItem) {
                    continue;
                }
                $lineSub = $this->lineSubtotal($line);
                if ($lineSub === 0) {
                    continue;
                }

                $portion = (int) RoundingMode::roundWith(($lineSub / max(1, $subtotal)) * $apply->amount);
                $app->addLine(
                    new Money($portion, $currency),
                    (new Money($lineSub, $currency))->percentage($percent, $rounding),
                    ['variant_id' => $line->variant_id],
                    $apply->amount < $requested->amount ? 'maximum_discount_amount' : null,
                );
            }
        }

        return $app;
    }
}
