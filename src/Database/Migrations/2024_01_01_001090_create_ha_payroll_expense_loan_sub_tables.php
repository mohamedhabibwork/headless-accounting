<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR/Payroll + Expense Management + Loans + Subscriptions.
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
        if (! Schema::hasTable('hr_employees')) {
            Schema::create('hr_employees', function (Blueprint $t) {
                $t->id();
                $t->string('employee_number', 32)->nullable();
                $t->string('first_name');
                $t->string('last_name');
                $t->string('email')->nullable();
                $t->string('phone', 32)->nullable();
                $t->string('position', 64)->nullable();
                $t->date('hire_date')->nullable();
                $t->date('end_date')->nullable();
                $t->char('currency', 3)->default(config('headless-accounting.currency.default'));
                $t->bigInteger('basic_salary_minor')->default(0);
                $t->unsignedSmallInteger('hours_per_week')->default(40);
                $t->unsignedSmallInteger('paid_leave_days')->default(25);
                $t->timestampsTz();
                $t->softDeletes();
            });
        }

        Schema::table('hr_employees', function (Blueprint $t) use ($p) {
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained($p.'branches')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained($p.'departments')->nullOnDelete();
        });

        Schema::create('hr_salary_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->string('name', 64);
            $t->string('type', 16);                            // 'earning' | 'deduction'
            $t->string('calc'); // enum: ['fixed', 'percent_of_basic', 'per_hour']
            $t->decimal('amount', 18, 4);
            $t->char('currency', 3);
            $t->timestampsTz();
        });

        Schema::create('hr_payroll_periods', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('name', 32);
            $t->date('starts_at');
            $t->date('ends_at');
            $t->date('pay_date')->nullable();
            $t->boolean('closed')->default(false);
            $t->timestampsTz();
        });

        Schema::create('hr_payroll_runs', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('period_id')->constrained('hr_payroll_periods')->cascadeOnDelete();
            $t->date('run_at');
            $t->string('state', 16)->default('computed');     // computed | posted | failed
            $t->bigInteger('gross_minor')->default(0);
            $t->bigInteger('net_minor')->default(0);
            $t->bigInteger('taxes_minor')->default(0);
            $t->bigInteger('social_insurance_minor')->default(0);
            $t->char('currency', 3);
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create('hr_payroll_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->string('component_name', 64);
            $t->string('type', 16);
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->timestampsTz();
        });

        Schema::create('hr_loans', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->foreignId('vendor_id')->nullable()->constrained($p.'vendors')->nullOnDelete();
            $t->string('name', 128);
            $t->string('kind'); // enum: ['employee', 'company', 'bank']
            $t->char('currency', 3);
            $t->bigInteger('principal_minor');
            $t->decimal('interest_rate_pct', 8, 4)->default(0);
            $t->unsignedSmallInteger('term_months');
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('state', 16)->default('active');     // active | settled | default
            $t->timestampsTz();
        });

        Schema::create('hr_loan_installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained('hr_loans')->cascadeOnDelete();
            $t->unsignedInteger('installment_no');
            $t->date('due_date');
            $t->char('currency', 3);
            $t->bigInteger('principal_minor');
            $t->bigInteger('interest_minor');
            $t->bigInteger('total_minor');
            $t->bigInteger('paid_minor')->default(0);
            $t->date('paid_at')->nullable();
            $t->string('state', 16)->default('pending');     // pending | paid | overdue
            $t->timestampsTz();
        });

        // Expense management
        Schema::create($p.'expense_claims', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained($p.'departments')->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained($p.'projects')->nullOnDelete();
            $t->string('number')->unique();
            $t->date('expense_date');
            $t->string('state', 16)->default('draft');        // draft | submitted | approved | reimbursed | rejected
            $t->char('currency', 3);
            $t->bigInteger('total_minor')->default(0);
            $t->foreignId('approval_id')->nullable(); // FK to wf_approval_instances is added in 001100 once that table exists
            $t->text('description')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'emp_vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $t->string('plate', 16);
            $t->string('description')->nullable();
            $t->decimal('mileage_rate_minor_per_km', 18, 4)->default(0.32);
            $t->timestampsTz();
        });

        Schema::create($p.'expense_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('claim_id')->constrained($p.'expense_claims')->cascadeOnDelete();
            $t->foreignId('account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();
            $t->date('date');
            $t->string('description');
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->decimal('tax_percent', 8, 4)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained($p.'tax_rates')->nullOnDelete();
            $t->decimal('mileage_km', 10, 2)->nullable();
            $t->foreignId('vehicle_id')->nullable()->constrained($p.'emp_vehicles')->nullOnDelete();
            $t->string('receipt_url', 255)->nullable();
            $t->timestampsTz();
        });

        // Subscriptions
        Schema::create($p.'sub_plans', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('name', 64);
            $t->text('description')->nullable();
            $t->char('currency', 3);
            $t->bigInteger('price_minor');
            $t->string('interval')->default('month'); // enum: ['day', 'week', 'month', 'quarter', 'year']
            $t->unsignedSmallInteger('interval_count')->default(1);
            $t->unsignedSmallInteger('trial_days')->default(0);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'sub_subscriptions', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained($p.'sub_plans');
            $t->foreignId('customer_id')->constrained($p.'customers')->cascadeOnDelete();
            $t->date('starts_at');
            $t->date('trial_ends_at')->nullable();
            $t->date('current_period_starts_at')->nullable();
            $t->date('current_period_ends_at')->nullable();
            $t->date('cancelled_at')->nullable();
            $t->string('state', 16)->default('active');         // trial | active | past_due | cancelled
            $t->decimal('quantity', 14, 4)->default(1);
            $t->bigInteger('deferred_revenue_minor')->default(0);
            $t->char('currency', 3);
            $t->timestampsTz();
        });

        Schema::create($p.'sub_invoices', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('subscription_id')->constrained($p.'sub_subscriptions')->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained($p.'invoices')->nullOnDelete();
            $t->date('issue_at');
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->bigInteger('recognized_minor')->default(0);
            $t->string('state', 16)->default('pending');        // pending | paid | failed
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'sub_invoices');
        Schema::dropIfExists($p.'sub_subscriptions');
        Schema::dropIfExists($p.'sub_plans');
        Schema::dropIfExists($p.'emp_vehicles');
        Schema::dropIfExists($p.'expense_lines');
        Schema::dropIfExists($p.'expense_claims');
        Schema::dropIfExists('hr_loan_installments');
        Schema::dropIfExists('hr_loans');
        Schema::dropIfExists('hr_payroll_lines');
        Schema::dropIfExists('hr_payroll_runs');
        Schema::dropIfExists('hr_payroll_periods');
        Schema::dropIfExists('hr_salary_components');
        Schema::dropIfExists('hr_employees');
    }
};
