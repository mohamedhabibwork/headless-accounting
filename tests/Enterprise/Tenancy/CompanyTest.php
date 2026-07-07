<?php

declare(strict_types=1);

use Headless\Accounting\Models\Company;
use Headless\Accounting\Tenancy\Branch;
use Headless\Accounting\Tenancy\CompanyContext;
use Headless\Accounting\Tenancy\CostCenter;
use Headless\Accounting\Tenancy\NumberSeries;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

describe('Multi-tenant foundation', function () {

    it('creates a company with branches and cost centers', function () {
        $co = Company::create([
            'code' => 'ACME', 'name' => 'Acme HQ', 'base_currency' => 'EUR',
        ]);

        $branch = Branch::create([
            'company_id' => $co->id, 'code' => 'HQ', 'name' => 'HQ',
            'is_head_office' => true,
        ]);

        $cc = CostCenter::create([
            'company_id' => $co->id, 'code' => 'ENG', 'name' => 'Engineering',
            'branch_id' => $branch->id,
        ]);

        expect($co->fresh()->branches)->toHaveCount(1);
        expect($branch->fresh()->costCenters)->toHaveCount(1);

        CompanyContext::set($co);
        expect(CompanyContext::id())->toBe($co->id);
        CompanyContext::forget();
    });

    it('mints a sequential document number via NumberSeries', function () {
        NumberSeries::for(null, 'INV')->firstOrCreate([]);
        $first = NumberSeries::for(null, 'INV')->next();
        $second = NumberSeries::for(null, 'INV')->next();
        expect($first)->toMatch('/^INV-\d{4}-\d{6}$/');
        expect($second)->not->toBe($first);
    });
});
