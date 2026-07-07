<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Resources;

use Headless\Accounting\Support\Config;

class ResolvedPriceResource extends JsonResource
{
    public function toArray($request): array
    {
        $locale = $request->header('Accept-Language', Config::string('headless-accounting.locale.default'));

        return [
            'amount_minor' => $this->resource->amount->amount,
            'currency' => $this->resource->amount->currency,
            'compare_at_minor' => $this->resource->compareAt?->amount,
            'on_sale' => $this->resource->isOnSale(),
            'tax_inclusive' => $this->resource->taxInclusive,
            'localized' => $this->resource->localized($locale),
            'price_list_ids' => $this->resource->appliedPriceListIds,
        ];
    }
}
