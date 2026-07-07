<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Contracts;

use Headless\Accounting\Discounts\EvaluationContext;

interface Condition
{
    public function passes(EvaluationContext $ctx): bool;

    public function setConfig(array $config): void;

    /** Stable type slug used in the JSON column. */
    public function type(): string;
}
