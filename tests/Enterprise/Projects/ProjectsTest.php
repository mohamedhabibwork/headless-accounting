<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\ProjectMilestone;
use Headless\Accounting\Projects\RevenueRecognition;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('Project accounting', function () {

    it('recognizes revenue based on completion %', function () {
        $co = Company::create(['code' => 'PJ', 'name' => 'Proj Co', 'base_currency' => 'EUR']);

        $project = Project::create([
            'company_id' => $co->id, 'code' => 'P1',
            'name' => 'Internal Portal',
            'total_contract_value_minor' => 1_000_000,
            'currency' => 'EUR',
            'progress_pct' => 35,
        ]);

        $recognition = (new RevenueRecognition)->percentageOfCompletion(
            $project,
            recognizedSoFar: 0,
        );

        expect($recognition['recognized_revenue_minor'])->toBe(350_000);
    });

    it('handles milestone revenue recognition', function () {
        $co = Company::create(['code' => 'PJ', 'name' => 'Proj Co', 'base_currency' => 'EUR']);
        $project = Project::create([
            'company_id' => $co->id, 'code' => 'P2', 'name' => 'Mobile',
            'total_contract_value_minor' => 600_000, 'currency' => 'EUR',
            'progress_pct' => 0,
        ]);

        ProjectMilestone::create([
            'project_id' => $project->id, 'name' => 'MVP',
            'pct' => 50, 'revenue_minor' => 300_000,
            'reached_at' => now(),
        ]);

        $out = (new RevenueRecognition)->milestoneBilling($project);
        expect($out['recognized_revenue_minor'])->toBeGreaterThanOrEqual(300_000);
    });
});
