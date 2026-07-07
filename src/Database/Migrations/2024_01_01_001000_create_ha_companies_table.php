<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant foundation. The package supports unlimited companies,
 * branches, cost centers and the like. The Company is the row-level
 * tenant boundary — every accounting record carries a `company_id`.
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
        Schema::create($p.'companies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->unique();                 // 'ACME', 'EU-1'
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('tax_id', 32)->nullable();              // EIN, VAT, GST …
            $t->string('registration_number', 64)->nullable();
            $t->char('base_currency', 3);
            $t->char('reporting_currency', 3)->nullable();     // optional separate reporting currency
            $t->string('locale', 8)->default(config('headless-accounting.locale.default'));
            $t->string('timezone', 64)->default('UTC');
            $t->date('fiscal_year_start')->nullable();         // e.g. 2026-01-01
            $t->string('logo_url', 255)->nullable();
            $t->json('branding')->nullable();
            $t->json('accounting_policies')->nullable();      // P&L override rules
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'branches', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 16);
            $t->string('name');
            $t->string('address_line1')->nullable();
            $t->string('address_line2')->nullable();
            $t->string('city')->nullable();
            $t->string('region', 64)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->string('postal_code', 32)->nullable();
            $t->string('phone', 32)->nullable();
            $t->boolean('is_head_office')->default(false);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'cost_centers', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained($p.'branches')->nullOnDelete();
            $t->string('code', 16);
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'profit_centers', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 16);
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'departments', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained($p.'branches')->nullOnDelete();
            $t->foreignId('manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->string('code', 16);
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'business_units', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 16);
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'business_units');
        Schema::dropIfExists($p.'departments');
        Schema::dropIfExists($p.'profit_centers');
        Schema::dropIfExists($p.'cost_centers');
        Schema::dropIfExists($p.'branches');
        Schema::dropIfExists($p.'companies');
    }
};
