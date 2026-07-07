<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\Bom;
use Headless\Accounting\Models\BomLine;
use Headless\Accounting\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomLineFactory extends Factory
{
    protected $model = BomLine::class;

    public function definition(): array
    {
        return [
            'bom_id' => Bom::factory(),
            'component_id' => Product::factory(),
            'quantity' => 1,
            'scrap_pct' => 0,
        ];
    }

    public function forBom(int $bomId): static
    {
        return $this->state(['bom_id' => $bomId]);
    }

    public function component(int $componentId): static
    {
        return $this->state(['component_id' => $componentId]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(['quantity' => $quantity]);
    }

    public function withScrap(float $scrapPct): static
    {
        return $this->state(['scrap_pct' => $scrapPct]);
    }
}
