<?php

declare(strict_types=1);

namespace Headless\Accounting\Pricing;

use Carbon\CarbonImmutable;
use Headless\Accounting\Currency\Money;
use Headless\Accounting\Models\Channel;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\Price;
use Headless\Accounting\Models\PriceList;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Support\Config;

/**
 * PricingResolver — resolves the effective unit price for a (variant,
 * channel, currency, customer-group, quantity, date) tuple.
 *
 * Resolution graph (each level consumes the parent's Money):
 *
 *   1. Channel-targeted   PriceList
 *   2. Customer-group     PriceList
 *   3. Tier-quantity      Price rows with min_quantity
 *   4. Variant base       Price (any price list with `min_quantity=1`)
 *   5. Product base       Same, on Product level
 *   6. Default            Fallback to Money::zero()
 *
 * Caching is left to the caller — pass an array key you compute.
 */
final class PricingResolver
{
    public function resolve(
        ProductVariant $variant,
        string $currency,
        ?Channel $channel = null,
        ?Customer $customer = null,
        int $quantity = 1,
        ?CarbonImmutable $at = null,
        ?string $locale = null,
    ): ResolvedPrice {
        $at ??= CarbonImmutable::now();
        $locale ??= Config::string('headless-accounting.locale.default');
        $applied = [];

        // 1) Find candidate price lists ordered by priority.
        $lists = $this->candidateLists($currency, $channel, $customer, $at);

        // 2) Walk lists looking for a price row whose subject matches our variant or its product.
        foreach ($lists as $list) {
            $row = Price::query()
                ->where('price_list_id', $list->id)
                ->where('currency', $currency)
                ->where('min_quantity', '<=', $quantity)
                ->where(function ($q) use ($variant) {
                    $q->where(fn ($s) => $s->where('subject_type', $variant->getMorphClass())->where('subject_id', $variant->getKey()))
                        ->orWhere(fn ($s) => $s->where('subject_type', $variant->product->getMorphClass())->where('subject_id', $variant->product->getKey()));
                })
                ->where(function ($q) use ($at) {
                    $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at->toDateString());
                })
                ->where(function ($q) use ($at) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $at->toDateString());
                })
                ->orderByDesc('min_quantity')
                ->first();

            if ($row) {
                $applied[] = $list->id;

                return new ResolvedPrice(
                    amount: new Money((int) $row->amount_minor, $currency),
                    compareAt: $row->compare_at_minor ? new Money((int) $row->compare_at_minor, $currency) : null,
                    taxInclusive: (bool) $row->tax_inclusive,
                    appliedPriceListIds: $applied,
                    locale: $locale,
                );
            }
        }

        // 3) Fallback: zero price + no compareAt.
        return new ResolvedPrice(Money::zero($currency), null, false, $applied, $locale);
    }

    /** @return PriceList[] */
    private function candidateLists(string $currency, ?Channel $channel, ?Customer $customer, CarbonImmutable $at): array
    {
        $q = PriceList::query()
            ->where('active', true)
            ->where('currency', $currency)
            ->where(function ($w) use ($at) {
                $w->whereNull('valid_from')->orWhere('valid_from', '<=', $at->toDateString());
            })
            ->where(function ($w) use ($at) {
                $w->whereNull('valid_until')->orWhere('valid_until', '>=', $at->toDateString());
            })
            ->orderBy('priority');

        if ($channel) {
            $q->where(function ($w) use ($channel) {
                $w->where('scope', 'channel')->where('scope_ref', $channel->code)
                    ->orWhere('scope', 'global');
            });
        } else {
            $q->where('scope', 'global');
        }

        if ($customer) {
            $groupTable = Config::string('headless-accounting.table_prefix', 'ha_').'customer_groups';
            $groupIds = $customer->groups()->pluck($groupTable.'.id')->all();
            if ($groupIds !== []) {
                $q->orWhere(function ($w) use ($groupIds) {
                    $w->where('scope', 'customer_group')->whereIn('scope_ref', $groupIds);
                });
            }
        }

        return $q->get()->all();
    }
}
