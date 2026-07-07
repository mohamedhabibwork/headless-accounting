<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Carbon\CarbonImmutable;
use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;

final class DateRangeCondition extends BaseCondition
{
    public function type(): string
    {
        return 'date_range';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $now = CarbonImmutable::now();
        $from = $this->get('starts_at');
        $until = $this->get('ends_at');
        if ($from && $now->lt(CarbonImmutable::parse($from))) {
            return false;
        }
        if ($until && $now->gt(CarbonImmutable::parse($until))) {
            return false;
        }

        return true;
    }
}
