<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function prefix(): string
    {
        return (string) config('headless-accounting.table_prefix', 'ha_');
    }

    public function up(): void
    {
        $p = $this->prefix();
        Schema::create($p.'bank_accounts', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->foreignId('chart_account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();
            $t->char('currency', 3);
            $t->string('iban', 64)->nullable();
            $t->string('bic', 16)->nullable();
            $t->string('bank_name')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'cash_accounts', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->foreignId('chart_account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();
            $t->char('currency', 3);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'bank_transfers', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('from_account_id')->nullable()->constrained($p.'bank_accounts')->nullOnDelete();
            $t->foreignId('to_account_id')->nullable()->constrained($p.'bank_accounts')->nullOnDelete();
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->decimal('fee_minor', 14, 0)->default(0);
            $t->decimal('fx_rate', 18, 8)->default(1.0);
            $t->date('transfer_date');
            $t->string('reference', 128)->nullable();
            $t->string('state', 16)->default('draft');
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'bank_reconciliations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('bank_account_id')->constrained($p.'bank_accounts')->cascadeOnDelete();
            $t->date('statement_date');
            $t->decimal('closing_balance_minor', 18, 0);
            $t->string('state', 16)->default('open');
            $t->json('metadata')->nullable();
            $t->unsignedInteger('matched_count')->default(0);
            $t->decimal('difference_minor', 14, 0)->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'bank_statement_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('reconciliation_id')->constrained($p.'bank_reconciliations')->cascadeOnDelete();
            $t->date('date');
            $t->text('description');
            $t->decimal('amount_minor', 18, 0);
            $t->string('reference', 128)->nullable();
            $t->string('matched_to_type')->nullable();
            $t->unsignedBigInteger('matched_to_id')->nullable();
            $t->index(['matched_to_type', 'matched_to_id']);
            $t->string('match_state', 16)->default('unmatched'); // unmatched | matched | suggested | ignored
            $t->timestampsTz();
        });

        Schema::create($p.'outstanding_checks', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('bank_account_id')->constrained($p.'bank_accounts')->cascadeOnDelete();
            $t->string('check_number', 64);
            $t->date('issued_at');
            $t->decimal('amount_minor', 18, 0);
            $t->char('currency', 3);
            $t->string('payee', 128)->nullable();
            $t->date('cleared_at')->nullable();
            $t->date('voided_at')->nullable();
            $t->string('state', 16)->default('outstanding');
            $t->timestampsTz();
            $t->index(['bank_account_id', 'state']);
        });

        Schema::create($p.'cash_positions', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->date('as_of');
            $t->char('currency', 3);
            $t->json('snapshot');              // { 'bank_acme': X, 'cash_main': Y, 'total': Z }
            $t->timestampsTz();
            $t->unique(['company_id', 'as_of', 'currency']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'cash_positions');
        Schema::dropIfExists($p.'outstanding_checks');
        Schema::dropIfExists($p.'bank_statement_lines');
        Schema::dropIfExists($p.'bank_reconciliations');
        Schema::dropIfExists($p.'bank_transfers');
        Schema::dropIfExists($p.'cash_accounts');
        Schema::dropIfExists($p.'bank_accounts');
    }
};
