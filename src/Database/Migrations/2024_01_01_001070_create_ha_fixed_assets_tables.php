<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed Assets — categories, register, depreciation, disposals, transfers,
 * revaluations and maintenance log.
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
        Schema::create($p.'asset_categories', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 32);
            $t->string('name');
            $t->string('default_depreciation_method', 32)->default('straight_line');
            $t->unsignedSmallInteger('default_useful_life_years')->default(5);
            $t->decimal('default_residual_pct', 5, 2)->default(0);
            $t->foreignId('asset_account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();
            $t->foreignId('accumulated_depreciation_account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();
            $t->foreignId('depreciation_expense_account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();
            $t->timestampsTz();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'assets', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('category_id')->constrained($p.'asset_categories')->cascadeOnDelete();
            $t->string('code', 32);                              // asset tag
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('serial_number', 128)->nullable();
            $t->date('purchase_date');
            $t->date('in_service_date');
            $t->date('disposed_at')->nullable();
            $t->char('currency', 3);
            $t->bigInteger('cost_minor')->default(0);
            $t->bigInteger('residual_minor')->default(0);
            $t->bigInteger('accumulated_depreciation_minor')->default(0);
            $t->string('depreciation_method', 32)->default('straight_line');
            $t->unsignedSmallInteger('useful_life_years');
            $t->decimal('depreciation_rate_pct', 8, 4)->nullable();    // for declining balance
            $t->foreignId('location_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->foreignId('custodian_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->string('state', 16)->default('active');         // active | disposed | transferred | sold | scrapped
            $t->foreignId('chart_account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();
            $t->timestampsTz();
            $t->softDeletes();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'depreciation_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('asset_id')->constrained($p.'assets')->cascadeOnDelete();
            $t->date('period');                                          // YYYY-MM-01
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->bigInteger('accumulated_minor');
            $t->bigInteger('book_value_minor');
            $t->unsignedInteger('fiscal_year');
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->boolean('posted')->default(false);
            $t->timestampsTz();
            $t->unique(['asset_id', 'period']);
        });

        Schema::create($p.'asset_disposals', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('asset_id')->constrained($p.'assets')->cascadeOnDelete();
            $t->date('disposed_at');
            $t->string('method', 32);                              // 'sold', 'scrapped', 'traded_in', 'lost'
            $t->bigInteger('proceeds_minor')->default(0);
            $t->bigInteger('cost_at_disposal_minor');
            $t->bigInteger('accumulated_at_disposal_minor');
            $t->bigInteger('gain_loss_minor');
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'asset_transfers', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('asset_id')->constrained($p.'assets')->cascadeOnDelete();
            $t->foreignId('from_location_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->foreignId('to_location_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->foreignId('from_custodian_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->foreignId('to_custodian_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->date('transferred_at');
            $t->text('reason')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'asset_revaluations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('asset_id')->constrained($p.'assets')->cascadeOnDelete();
            $t->date('revalued_at');
            $t->bigInteger('previous_cost_minor');
            $t->bigInteger('new_cost_minor');
            $t->bigInteger('revaluation_reserve_delta_minor');
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->text('reason')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'asset_maintenance', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('asset_id')->constrained($p.'assets')->cascadeOnDelete();
            $t->date('service_at');
            $t->string('action', 64);
            $t->bigInteger('cost_minor')->default(0);
            $t->char('currency', 3);
            $t->foreignId('vendor_id')->nullable()->constrained($p.'vendors')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'asset_maintenance');
        Schema::dropIfExists($p.'asset_revaluations');
        Schema::dropIfExists($p.'asset_transfers');
        Schema::dropIfExists($p.'asset_disposals');
        Schema::dropIfExists($p.'depreciation_lines');
        Schema::dropIfExists($p.'assets');
        Schema::dropIfExists($p.'asset_categories');
    }
};
