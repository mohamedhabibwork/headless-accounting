<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Carbon\CarbonImmutable;
use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;

final class DayOfWeekCondition extends BaseCondition
{
    public function type(): string
    {
        return 'day_of_week';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $days = array_map('strtolower', (array) $this->get('days', []));
        if ($days === []) {
            return true;
        }
        $today = strtolower(CarbonImmutable::now()->format('D')); // mon, tue, …

        return in_array($today, $days, true);
    }
}
