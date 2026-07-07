<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\FixedAssets\Asset;
use Headless\Accounting\FixedAssets\AssetCategory;
use Headless\Accounting\FixedAssets\DepreciationEngine;
use Headless\Accounting\Models\Account;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('Fixed Asset depreciation', function () {
    it('straight-line over asset life', function () {
        $co = Company::create(['code' => 'FA', 'name' => 'FA Co', 'base_currency' => 'EUR']);
        $cat = AssetCategory::create([
            'company_id' => $co->id, 'code' => 'PC',
            'name' => 'Computer', 'depreciation_method' => 'straight_line',
            'useful_life_months' => 24,
        ]);

        $asset = Asset::create([
            'company_id' => $co->id,
            'category_id' => $cat->id,
            'code' => 'A001',
            'name' => 'Laptop',
            'acquisition_cost_minor' => 240000,
            'residual_value_minor' => 0,
            'acquired_at' => '2025-01-01',
            'in_service_at' => '2025-01-01',
            'state' => 'in_service',
        ]);

        $depAccount = Account::where('code', '6900')->firstOrFail();
        $assetAccount = Account::where('code', '1500')->firstOrFail();
        $engine = new DepreciationEngine;
        $schedule = $engine->schedule($asset, $depAccount, $assetAccount, 12);

        expect($schedule->count())->toBe(24);
        expect($schedule->sum('amount_minor'))->toBe(240000);
    });
});
