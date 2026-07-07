<?php

declare(strict_types=1);

namespace Headless\Accounting\Integration;

use Headless\Accounting\Models\SaasSubscription;
use Headless\Accounting\Models\SaasUsageCounter;
use Headless\Accounting\Support\Config;
use Headless\Accounting\Tenancy\CompanyContext;

/**
 * SaasResolver — checks the active CompanyContext against the active
 * SaasSubscription and reports whether a feature is enabled, the
 * quota thresholds, and usage counters.
 *
 *   app(SaasResolver::class)->allows('orders.create');   // bool
 *   app(SaasResolver::class)->remaining('orders');      // int (-1 = unlimited)
 */
class SaasResolver
{
    public function activeSubscription(): ?SaasSubscription
    {
        $company = CompanyContext::current();
        if (! $company) {
            return null;
        }

        return SaasSubscription::query()
            ->where('company_id', $company->id)
            ->whereIn('state', ['trial', 'active'])
            ->latest('id')
            ->first();
    }

    public function allows(string $featureKey): bool
    {
        $sub = $this->activeSubscription();
        if (! $sub) {
            return Config::bool('headless-accounting.saas.allow_by_default', true);
        }

        $features = (array) ($sub->modules_enabled ?? []);
        $planFeatures = (array) ($sub->plan?->features ?? []);

        return in_array($featureKey, $features, true) || in_array('*', $planFeatures, true);
    }

    public function remaining(string $limitKey): int
    {
        $sub = $this->activeSubscription();
        if (! $sub) {
            return -1;
        }

        $limits = (array) ($sub->plan?->limits ?? []);
        if (! isset($limits[$limitKey])) {
            return -1;
        }            // no cap
        $limit = (int) $limits[$limitKey];

        $usage = (int) SaasUsageCounter::query()
            ->where('subscription_id', $sub->id)
            ->where('metric_key', $limitKey)
            ->whereDate('period', now()->startOfMonth()->toDateString())
            ->sum('count');

        return max(0, $limit - $usage);
    }
}
