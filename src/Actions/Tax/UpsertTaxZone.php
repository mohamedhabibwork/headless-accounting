<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Tax;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\TaxZone;
use Headless\Accounting\Models\TaxZoneMember;

/**
 * UpsertTaxZone — creates or updates a {@see TaxZone} and its members
 * atomically. Members are matched by (country, region, postal_pattern).
 *
 *   $zone = (new UpsertTaxZone)->execute(
 *       code: 'eu-vat',
 *       name: 'EU VAT zone',
 *       members: [
 *           ['country_code' => 'FR', 'operator' => 'or'],
 *           ['country_code' => 'DE', 'operator' => 'or'],
 *           ['country_code' => 'IT', 'operator' => 'or'],
 *       ],
 *   );
 */
final class UpsertTaxZone extends Action
{
    protected function handle(
        string $code,
        string $name,
        array $members = [],
        ?string $description = null,
        bool $active = true,
    ): TaxZone {
        $zone = TaxZone::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'description' => $description, 'active' => $active],
        );

        if ($members !== []) {
            // Wipe and re-insert. Members are simple enough to make that safe and clear.
            TaxZoneMember::query()->where('zone_id', $zone->id)->delete();
            foreach ($members as $m) {
                TaxZoneMember::create([
                    'zone_id' => $zone->id,
                    'country_code' => $m['country_code'] ?? null,
                    'region' => $m['region'] ?? null,
                    'postal_code_pattern' => $m['postal_code_pattern'] ?? null,
                    'operator' => $m['operator'] ?? 'or',
                ]);
            }
        }

        return $zone->fresh('members');
    }
}
