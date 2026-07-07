<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Drivers;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseDiscountDriver;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;
use InvalidArgumentException;

/**
 * FixedAmountDiscount — applies a flat reduction in a specific currency.
 *
 * If `distribute_per_line=true`, the engine attempts to spread the
 * discount evenly across candidate lines (largest remainder first),
 * capping each line at the line subtotal.
 *
 * Configuration:
 *   - amount                       (int, minor units)
 *   - currency                     (string, ISO-4217, optional — defaults to order currency)
 *   - distribute_per_line          (bool, default true)
 */
final class FixedAmountDiscount extends BaseDiscountDriver
{
    public function type(): string
    {
        return 'fixed';
    }

    public function calculate(EvaluationContext $ctx, iterable $candidateLines): DiscountApplication
    {
        $amount = (int) $this->config('amount', 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Fixed discount amount must be > 0.');
        }

        $currency = $this->config('currency') ?? $this->currency($ctx);
        $money = new Money($amount, $currency);
        $subtotal = $this->subtotal($candidateLines);

        // Never give more discount than the subtotal we're acting on.
        $apply = $subtotal > 0 && $amount > $subtotal
            ? new Money($subtotal, $currency)
            : $money;

        $app = new DiscountApplication(
            discountId: $ctx->order?->id ?? 0,
            discountName: $this->config('__name', 'fixed'),
            total: $apply,
            requested: $money,
        );

        if ($this->config('distribute_per_line', true) && $subtotal > 0) {
            $collected = 0;
            $lines = [];
            foreach ($candidateLines as $line) {
                if ($line instanceof OrderItem) {
                    $lines[] = $line;
                }
            }
            if ($lines !== []) {
                $shares = $apply->allocate(count($lines));    // returns [Money, …]
                foreach ($lines as $i => $line) {
                    $share = $shares[$i];
                    $app->addLine(
                        $share,
                        new Money(min($amount, $this->lineSubtotal($line)), $currency),
                        ['variant_id' => $line->variant_id],
                        $amount > $subtotal ? 'exceeds_subtotal' : null,
                    );
                    $collected += $share->amount;
                }
            }
        }

        return $app;
    }
}
