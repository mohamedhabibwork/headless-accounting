<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Seeders;

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Models\TaxClass;
use Headless\Accounting\Models\TaxZone;
use Headless\Accounting\Models\TaxZoneMember;
use Illuminate\Database\Seeder;

/**
 * InitSeeder — bootstraps the package with the baseline reference data it
 * needs to operate. Run after `migrate` on a fresh install:
 *
 *   php artisan db:seed --class="Headless\\Accounting\\Database\\Seeders\\InitSeeder"
 *
 * Idempotent — safe to re-run.
 *
 *   1. Currencies    (ISO-4217 from registry → ha_currencies)
 *   2. Channels      (per config/headless-accounting.php → ha_channels)
 *   3. TaxClasses    (Standard / Reduced / Zero / Digital B2C / Digital B2B)
 *   4. TaxZones      (EU-VAT + US-states skeleton)
 *   5. ChartOfAccounts (sensible default)
 *   6. Fiscal year + periods for the current year
 */
class InitSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CurrenciesSeeder::class);
        $this->call(ChannelsSeeder::class);

        $this->seedTaxClasses();
        $this->seedTaxZones();

        (new DefaultChartOfAccounts)->install();

        $this->call(FiscalYearSeeder::class);
    }

    private function seedTaxClasses(): void
    {
        foreach ([
            ['Standard',   'standard'],
            ['Reduced',    'reduced'],
            ['Zero-rated', 'zero'],
            ['Digital services (B2C)', 'digital-b2c'],
            ['Digital services (B2B reverse-charge)', 'digital-b2b'],
        ] as [$name, $slug]) {
            TaxClass::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }

    private function seedTaxZones(): void
    {
        $eu = TaxZone::query()->updateOrCreate(['code' => 'eu-vat'], ['name' => 'European Union VAT zone', 'active' => true]);
        foreach (['FR', 'DE', 'IT', 'ES', 'NL', 'BE', 'PT', 'AT', 'IE', 'FI', 'SE', 'DK', 'PL', 'CZ', 'SK', 'HU', 'GR'] as $iso) {
            TaxZoneMember::query()->updateOrCreate(
                ['zone_id' => $eu->id, 'country_code' => $iso],
                ['operator' => 'or'],
            );
        }

        $us = TaxZone::query()->updateOrCreate(['code' => 'us-states'], ['name' => 'United States', 'active' => true]);
        foreach (['CA', 'NY', 'TX', 'WA', 'FL', 'IL', 'PA', 'OH'] as $usState) {
            TaxZoneMember::query()->updateOrCreate(
                ['zone_id' => $us->id, 'country_code' => 'US', 'region' => $usState],
                ['operator' => 'or'],
            );
        }
    }
}
