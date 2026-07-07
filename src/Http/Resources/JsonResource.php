<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Resources;

use Headless\Accounting\Models\Order;
use Illuminate\Http\Resources\Json\JsonResource as BaseJsonResource;

abstract class JsonResource extends BaseJsonResource
{
    public function toArray($request): array
    {
        return $this->resource instanceof Order
            ? $this->asOrder($request)
            : $this->asGeneric($request);
    }

    protected function asGeneric($request): array
    {
        return method_exists($this, 'payload')
            ? $this->payload()
            : [];
    }

    protected function asOrder($request): array
    {
        return [
            'id' => $this->resource->id,
            'number' => $this->resource->number,
            'state' => $this->resource->state,
            'currency' => $this->resource->currency,
            'totals' => [
                'subtotal' => $this->resource->subtotal_minor,
                'discount' => $this->resource->discount_total_minor,
                'tax' => $this->resource->tax_total_minor,
                'shipping' => $this->resource->shipping_minor,
                'grand' => $this->resource->grand_total_minor,
                'paid' => $this->resource->totalPaid(),
                'balance' => $this->resource->balanceDue(),
            ],
            'items' => $this->resource->items->map(fn ($i) => [
                'id' => $i->id, 'variant_id' => $i->variant_id, 'sku' => $i->sku,
                'name' => $i->name, 'quantity' => $i->quantity,
                'unit_price' => $i->unit_price_minor, 'unit_tax' => $i->unit_tax_minor,
            ])->all(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
