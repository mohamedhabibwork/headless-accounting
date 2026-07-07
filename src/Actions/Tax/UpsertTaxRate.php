<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Tax;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\TaxClass;
use Headless\Accounting\Models\TaxRate;
use Headless\Accounting\Models\TaxZone;

/**
 * UpsertTaxRate — creates or updates a {@see TaxRate} keyed by
 * (zone_id, name, tax_class_id). Compound tax is exposed via
 * `$compound` so compound-VAT scenarios can be modeled.
 */
final class UpsertTaxRate extends Action
{
    protected function handle(
        TaxZone $zone,
        string $name,
        float $percent,
        ?TaxClass $taxClass = null,
        bool $compound = false,
        int $priority = 1,
        bool $active = true,
    ): TaxRate {
        return TaxRate::query()->updateOrCreate(
            [
                'zone_id' => $zone->id,
                'name' => $name,
                'tax_class_id' => $taxClass?->id,
            ],
            [
                'percent' => $percent,
                'compound' => $compound,
                'priority' => $priority,
                'active' => $active,
            ],
        );
    }
}
