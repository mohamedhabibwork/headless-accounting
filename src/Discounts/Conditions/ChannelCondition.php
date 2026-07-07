<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;

final class ChannelCondition extends BaseCondition
{
    public function type(): string
    {
        return 'channel';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $channels = (array) $this->get('channels', []);
        if ($channels === []) {
            return true;
        }

        $code = $ctx->channel?->code ?? ($ctx->order?->channel_code ?? null);

        return $code ? in_array((string) $code, $channels, true) : false;
    }
}
