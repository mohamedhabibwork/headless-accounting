<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable Approval Workflow engine.
 *
 *   WorkflowDefinition (per "thing to approve", e.g. invoice)
 *       ↓ has many
 *   WorkflowStep (level 1..N, type=user|role|amount_gate|parallel_branch)
 *       ↓ has many
 *   ApprovalInstance (live run, references the source document)
 *       ↓ has many
 *   ApprovalAction (one per step, decision + actor + timestamp)
 */
return new class extends Migration
{
    private function prefix(): string
    {
        return (string) config('headless-accounting.table_prefix', 'ha_');
    }

    public function up(): void
    {
        $p = $this->prefix();
        Schema::create('wf_definitions', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('scope', 64);                                  // 'invoice', 'payment', 'journal', 'expense', 'purchase', 'asset'
            $t->string('name');
            $t->text('description')->nullable();
            $t->json('config')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'scope', 'name']);
        });

        Schema::create('wf_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('definition_id')->constrained('wf_definitions')->cascadeOnDelete();
            $t->unsignedSmallInteger('order');                        // 1..N
            $t->string('name', 128);
            $t->string('approver_type', 32);                          // 'user' | 'role' | 'manager' | 'amount_gate'
            $t->json('approver_config')->nullable();                  // ['role' => 'accountant']
            $t->decimal('min_amount_minor', 18, 0)->nullable();       // amount_gate
            $t->decimal('max_amount_minor', 18, 0)->nullable();
            $t->string('mode')->default('any');          // for parallel branches; enum: ['any', 'all']
            $t->boolean('required')->default(true);
            $t->timestampsTz();
        });

        Schema::create('wf_approval_instances', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('definition_id')->constrained('wf_definitions')->cascadeOnDelete();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->morphs('subject');
            $t->string('state', 16)->default('pending');               // pending | approved | rejected | cancelled
            $t->unsignedSmallInteger('current_step')->default(0);
            $t->timestampsTz();
        });

        Schema::create('wf_approval_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instance_id')->constrained('wf_approval_instances')->cascadeOnDelete();
            $t->foreignId('step_id')->nullable()->constrained('wf_steps')->nullOnDelete();
            $t->string('decision', 16);                                  // approved | rejected | delegated | requested_changes
            $t->morphs('actor');
            $t->text('notes')->nullable();
            $t->timestampTz('decision_at');
            $t->timestampsTz();
        });

        Schema::create('wf_delegations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('from_user_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->foreignId('to_user_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->morphs('scope');                                          // workflow step
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        // Add the deferred FK from ha_expense_claims.approval_id → wf_approval_instances.id
        // (originally declared in 001090 but deferred to avoid forward FK to a not-yet-created table)
        Schema::table($p.'expense_claims', function (Blueprint $t) {
            $t->foreign('approval_id')->references('id')->on('wf_approval_instances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_delegations');
        Schema::dropIfExists('wf_approval_actions');
        Schema::dropIfExists('wf_approval_instances');
        Schema::dropIfExists('wf_steps');
        Schema::dropIfExists('wf_definitions');
    }
};
