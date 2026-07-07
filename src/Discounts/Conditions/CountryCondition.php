<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Conditions;

use Headless\Accounting\Discounts\BaseCondition;
use Headless\Accounting\Discounts\EvaluationContext;

final class CountryCondition extends BaseCondition
{
    public function type(): string
    {
        return 'country';
    }

    public function passes(EvaluationContext $ctx): bool
    {
        $countries = array_map('strtoupper', (array) $this->get('countries', []));
        if ($countries === []) {
            return true;
        }
        $country = strtoupper((string) ($ctx->shippingAddress?->country_code ?? ''));

        return $country !== '' && in_array($country, $countries, true);
    }
}
