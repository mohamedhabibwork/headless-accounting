<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\FulfillmentPlan;

/**
 * FulfillmentPlanCreated — fired after a {@see FulfillmentPlan} is built
 * and its allocations + shipping options have been persisted.
 */
class FulfillmentPlanCreated extends Event
{
    public function __construct(public readonly FulfillmentPlan $plan) {}
}
