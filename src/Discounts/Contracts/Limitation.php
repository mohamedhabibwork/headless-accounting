<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Contracts;

use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;

interface Limitation
{
    public function apply(EvaluationContext $ctx, DiscountApplication $application): DiscountApplication;

    public function setConfig(array $config): void;

    public function type(): string;
}
