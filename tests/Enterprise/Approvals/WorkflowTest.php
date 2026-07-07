<?php

declare(strict_types=1);

use Headless\Accounting\Approve\ApprovalInstance;
use Headless\Accounting\Approve\WorkflowDefinition;
use Headless\Accounting\Approve\WorkflowEngine;
use Headless\Accounting\Models\User;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

describe('Approval workflow engine', function () {

    it('runs sequential single-approver flow', function () {
        $co = Company::create(['code' => 'AP', 'name' => 'Approve Co', 'base_currency' => 'EUR']);
        $wf = WorkflowDefinition::create([
            'company_id' => $co->id, 'code' => 'PO_APPROVE', 'name' => 'PO Approval',
            'subject_type' => 'purchase_order', 'active' => true,
            'config' => ['levels' => [
                ['min_amount' => 0, 'role' => 'manager'],
                ['min_amount' => 1000, 'role' => 'director'],
            ]],
        ]);

        $instance = ApprovalInstance::create([
            'workflow_id' => $wf->id,
            'subject_type' => 'purchase_order',
            'subject_id' => 42,
            'amount_minor' => 5000,
            'state' => 'pending',
        ]);

        $engine = new WorkflowEngine;
        $engine->start($instance);
        expect($instance->fresh()->state)->toBe('pending');

        $manager = User::create(['name' => 'Manager', 'email' => 'm@x']);
        $director = User::create(['name' => 'Director', 'email' => 'd@x']);

        $engine->approve($instance->fresh(), $manager);
        expect($instance->fresh()->state)->toBe('pending');     // still pending, needs director

        $engine->approve($instance->fresh(), $director);
        expect($instance->fresh()->state)->toBe('approved');
    });
});
