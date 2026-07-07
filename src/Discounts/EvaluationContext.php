<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Models\Address;
use Headless\Accounting\Models\Channel;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Support\Config;

/**
 * EvaluationContext — snapshot of everything the discount engine needs
 * to evaluate a {@see Discount} against a
 * candidate order/cart. Built once per resolution pass; passed
 * read-only by reference to every condition/limitation.
 */
final class EvaluationContext
{
    /**
     * @param  Order|null  $order
     * @param  iterable<OrderItem>  $items
     * @param  Customer|null  $customer
     * @param  Channel|null  $channel
     * @param  Address|null  $shippingAddress
     * @param  Address|null  $billingAddress
     * @param  array{coupon?:string, intent?:string}  $extras
     */
    public readonly string $locale;

    public function __construct(
        public readonly mixed $order = null,
        public readonly iterable $items = [],
        public readonly mixed $customer = null,
        public readonly mixed $channel = null,
        public readonly mixed $shippingAddress = null,
        public readonly mixed $billingAddress = null,
        public readonly array $extras = [],
        ?string $locale = null,
    ) {
        $this->locale = $locale ?? Config::string('headless-accounting.locale.default', 'en');
    }

    public function coupon(): ?string
    {
        return $this->extras['coupon'] ?? null;
    }

    public function intent(): ?string
    {
        return $this->extras['intent'] ?? null;
    }
}
