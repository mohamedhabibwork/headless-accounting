<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Resources;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->asOrder($request);
    }
}
