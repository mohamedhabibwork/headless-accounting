<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Resources;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'tax_class_id' => $this->resource->tax_class_id,
            'variants' => $this->resource->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->name,
            ])->all(),
        ];
    }
}
