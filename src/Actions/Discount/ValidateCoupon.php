<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Discount;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Discounts\ConditionFactory;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\Cart;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Support\Config;
use InvalidArgumentException;

/**
 * ValidateCoupon — checks whether a coupon code can be redeemed on the
 * given order by walking the conditions of *any* discount that owns
 * that code. Returns the qualifying Discount or throws.
 *
 * Useful as a pre-checkout endpoint: "Will this code work for me?"
 */
final class ValidateCoupon extends Action
{
    public function __construct(private readonly ConditionFactory $conditions) {}

    protected function handle(
        string $code,
        Order|null|Cart $subject = null,
        ?Customer $customer = null,
        ?string $currency = null,
        ?string $locale = null,
    ): Discount {
        $currency ??= Config::string('headless-accounting.currency.default');
        $locale ??= Config::string('headless-accounting.locale.default');

        if ($code === '' || $code === null) {
            throw new InvalidArgumentException('Coupon code is required.');
        }

        $discount = Discount::query()
            ->where('code', $code)
            ->where('active', true)
            ->first();

        if (! $discount) {
            throw new InvalidArgumentException("Unknown or inactive coupon code: {$code}.");
        }

        $items = $subject instanceof \Headless\Accounting\Models\Order
            ? iterator_to_array($subject->items()->cursor())
            : iterator_to_array($subject->items()->cursor());

        $ctx = new EvaluationContext(
            order: $subject instanceof \Headless\Accounting\Models\Order ? $subject : null,
            items: $items,
            customer: $customer,
            locale: $locale,
            extras: ['coupon' => $code],
        );

        foreach ($discount->conditions as $cond) {
            $instance = $this->conditions->make((string) $cond->type);
            $instance->setConfig((array) $cond->config);
            if (! $instance->passes($ctx)) {
                throw new InvalidArgumentException("Coupon {$code} is not valid for this cart.");
            }
        }

        // Check the discount hasn't hit any usage caps.
        if ($discount->usages()->where('customer_id', $customer?->getKey())->exists()) {
            // Single-use limitations are handled via the engine; we only check "already used".
            foreach ($discount->limitations as $lim) {
                if ($lim->type === 'max_per_customer' && (int) ($lim->config['max'] ?? 0) === 1) {
                    throw new InvalidArgumentException("Coupon {$code} has already been used.");
                }
            }
        }

        return $discount;
    }
}
