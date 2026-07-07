<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budgeting + Project Accounting + Wage/Salary components.
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
        // Projects (must be created before ha_budgets which FK-references it)
        Schema::create($p.'projects', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->foreignId('customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->bigInteger('budget_minor')->default(0);
            $t->char('currency', 3)->default(config('headless-accounting.currency.default'));
            $t->decimal('progress_pct', 5, 2)->default(0);
            $t->string('state', 16)->default('planning'); // planning | active | billable | on_hold | completed | cancelled
            $t->timestampsTz();
        });

        Schema::create($p.'project_tasks', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('project_id')->constrained($p.'projects')->cascadeOnDelete();
            $t->string('name');
            $t->date('due_at')->nullable();
            $t->boolean('billable')->default(true);
            $t->unsignedInteger('estimated_minutes')->default(0);
            $t->timestampsTz();
        });

        // Budgeting
        Schema::create($p.'budgets', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('name');
            $t->string('scope'); // enum: ['annual', 'departmental', 'project', 'cost_center']
            $t->foreignId('department_id')->nullable()->constrained($p.'departments')->nullOnDelete();
            $t->foreignId('cost_center_id')->nullable()->constrained($p.'cost_centers')->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained($p.'projects')->nullOnDelete();
            $t->unsignedInteger('year');
            $t->char('currency', 3);
            $t->boolean('approved')->default(false);
            $t->timestampsTz();
        });

        Schema::create($p.'budget_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('budget_id')->constrained($p.'budgets')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained($p.'accounts')->cascadeOnDelete();
            $t->char('currency', 3);
            $t->unsignedTinyInteger('month')->nullable();              // null = split evenly across year
            $t->bigInteger('planned_minor');
            $t->bigInteger('revised_minor')->default(0);
            $t->bigInteger('actual_minor')->default(0);                // populated by service
            $t->decimal('variance_pct', 8, 4)->default(0);
            $t->timestampsTz();
            $t->unique(['budget_id', 'account_id', 'month']);
        });

        Schema::create($p.'forecasts', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('budget_id')->nullable()->constrained($p.'budgets')->nullOnDelete();
            $t->foreignId('account_id')->constrained($p.'accounts')->cascadeOnDelete();
            $t->unsignedInteger('month');
            $t->decimal('forecast_minor', 18, 4);
            $t->decimal('confidence', 5, 4)->default(1.0);
            $t->timestampsTz();
        });

        Schema::create($p.'project_time_bills', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('project_id')->constrained($p.'projects')->cascadeOnDelete();
            $t->foreignId('task_id')->nullable()->constrained($p.'project_tasks')->nullOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->date('date');
            $t->unsignedInteger('minutes');
            $t->decimal('hourly_rate_minor', 18, 4);
            $t->char('currency', 3);
            $t->decimal('amount_minor', 18, 0);
            $t->string('state', 16)->default('draft');      // draft | invoiced
            $t->foreignId('invoice_id')->nullable()->constrained($p.'invoices')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'project_milestones', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('project_id')->constrained($p.'projects')->cascadeOnDelete();
            $t->string('name');
            $t->date('due_at')->nullable();
            $t->date('achieved_at')->nullable();
            $t->bigInteger('revenue_minor')->default(0);     // billable milestone
            $t->char('currency', 3);
            $t->boolean('invoiced')->default(false);
            $t->foreignId('invoice_id')->nullable()->constrained($p.'invoices')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'project_wip', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('project_id')->constrained($p.'projects')->cascadeOnDelete();
            $t->date('as_of');
            $t->char('currency', 3);
            $t->bigInteger('costs_minor')->default(0);
            $t->bigInteger('recognized_revenue_minor')->default(0);
            $t->bigInteger('over_under_minor')->default(0);
            $t->timestampsTz();
            $t->unique(['project_id', 'as_of', 'currency']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'project_wip');
        Schema::dropIfExists($p.'project_milestones');
        Schema::dropIfExists($p.'project_time_bills');
        Schema::dropIfExists($p.'project_tasks');
        Schema::dropIfExists($p.'projects');
        Schema::dropIfExists($p.'forecasts');
        Schema::dropIfExists($p.'budget_lines');
        Schema::dropIfExists($p.'budgets');
    }
};
