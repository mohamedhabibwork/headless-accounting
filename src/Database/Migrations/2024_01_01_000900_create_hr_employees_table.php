<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `hr_employees` table early so that other enterprise migrations
 * (e.g. `ha_departments`) can declare foreign keys against it. The
 * department/branch/company FKs on this table are added later in
 * `001090_create_ha_payroll_expense_loan_sub_tables.php` once those tables
 * exist.
 */
return new class extends Migration
{
    private function prefix(): string
    {
        return (string) config('headless-accounting.table_prefix', 'ha_');
    }

    public function up(): void
    {
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

        Schema::table('hr_employees', function (Blueprint $t) {
            $t->foreignId('manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
    }
};
