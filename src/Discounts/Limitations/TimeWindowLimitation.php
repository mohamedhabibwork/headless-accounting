<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Limitations;

use Carbon\CarbonImmutable;
use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseLimitation;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Support\Config;

final class TimeWindowLimitation extends BaseLimitation
{
    public function type(): string
    {
        return 'time_window';
    }

    public function apply(EvaluationContext $ctx, DiscountApplication $application): DiscountApplication
    {
        $tz = (string) $this->get('timezone', Config::string('app.timezone', 'UTC'));
        $startHm = (string) $this->get('starts_at', '00:00');
        $endHm = (string) $this->get('ends_at', '23:59');

        $now = CarbonImmutable::now($tz);
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d').' '.$startHm, $tz);
        $end = CarbonImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d').' '.$endHm, $tz);

        if ($now->between($start, $end)) {
            return $application;
        }

        return new DiscountApplication(
            discountId: $application->discountId,
            discountName: $application->discountName,
            total: Money::zero($application->total->currency),
            requested: $application->requested,
        );
    }
}
