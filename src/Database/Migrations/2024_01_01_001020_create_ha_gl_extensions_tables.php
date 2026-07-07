<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring journal templates, journal templates, company-scoped audit
 * events, and the GL configuration table per company (accounting
 * policies).
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
        Schema::create($p.'journal_templates', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 32);
            $t->string('name');
            $t->string('description', 255)->nullable();
            $t->char('currency', 3);
            $t->json('lines');                                    // [['account' => '1200', 'debit' => 0, 'credit' => 1000, 'memo' => 'X'], ...]
            $t->json('tags')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'recurring_journals', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('template_id')->nullable()->constrained($p.'journal_templates')->nullOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->char('currency', 3);
            $t->string('frequency'); // enum: ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly']
            $t->unsignedTinyInteger('day_of_month')->nullable();
            $t->unsignedTinyInteger('day_of_week')->nullable();        // 1..7
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->date('next_run_at')->nullable();
            $t->date('last_run_at')->nullable();
            $t->unsignedInteger('max_occurrences')->nullable();
            $t->unsignedInteger('occurrences_count')->default(0);
            $t->json('lines');                                    // overridden lines if not using a template
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->index(['active', 'next_run_at']);
        });

        Schema::create($p.'recurring_journal_runs', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('recurring_journal_id')->constrained($p.'recurring_journals')->cascadeOnDelete();
            $t->timestampTz('run_at');
            $t->string('status')->default('pending'); // enum: ['pending', 'posted', 'failed', 'skipped']
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->text('error')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'account_policies', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('key', 64);    // e.g. 'inventory_valuation_method' => 'fifo'
            $t->text('value')->nullable();
            $t->text('description')->nullable();
            $t->timestampsTz();
            $t->unique(['company_id', 'key']);
        });

        Schema::create($p.'account_aliases', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('account_id')->constrained($p.'accounts')->cascadeOnDelete();
            $t->string('alias', 64);
            $t->string('locale', 8)->nullable();
            $t->timestampsTz();
            $t->unique(['alias', 'locale']);
        });

        Schema::create($p.'audit_events', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->morphs('subject');                                   // polymorphic Eloquent model
            $t->string('event', 64);                                 // 'created', 'updated', 'deleted', 'state.changed', …
            $t->string('actor_type', 64)->nullable();
            $t->string('actor_id', 64)->nullable();
            $t->string('ip', 45)->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->json('metadata')->nullable();
            $t->timestampTz('occurred_at');
            $t->timestampsTz();
            $t->index(['company_id', 'event', 'occurred_at']);
        });

        Schema::create($p.'login_histories', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->string('subject_type', 64)->nullable();
            $t->string('subject_id', 64)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 256)->nullable();
            $t->boolean('success')->default(true);
            $t->timestampTz('logged_in_at');
            $t->timestampsTz();
        });

        Schema::create($p.'export_logs', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->string('format', 16);                                // csv, pdf, json
            $t->string('report', 64);
            $t->json('filters')->nullable();
            $t->morphs('actor');
            $t->string('ip', 45)->nullable();
            $t->integer('row_count')->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'e_invoices', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->morphs('source');                                // invoice, credit note, etc.
            $t->string('format', 16);                            // 'ubl2.1', 'xrechnung', 'fatturapa', 'cfdi40'
            $t->char('direction', 4);                            // 'in', 'out'
            $t->string('document_id', 64);                       // unique id from authority
            $t->string('access_url', 255)->nullable();
            $t->dateTime('generated_at')->nullable();
            $t->dateTime('transmitted_at')->nullable();
            $t->dateTime('received_at')->nullable();
            $t->text('error')->nullable();
            $t->string('status', 32)->default('draft');
            $t->json('payload')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'retention_policies', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('scope', 64);                              // 'audit_event', 'document', etc.
            $t->unsignedInteger('retention_days');
            $t->boolean('anonymize')->default(true);
            $t->boolean('hard_delete')->default(false);
            $t->timestampsTz();
            $t->unique(['company_id', 'scope']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'retention_policies');
        Schema::dropIfExists($p.'e_invoices');
        Schema::dropIfExists($p.'export_logs');
        Schema::dropIfExists($p.'login_histories');
        Schema::dropIfExists($p.'audit_events');
        Schema::dropIfExists($p.'account_aliases');
        Schema::dropIfExists($p.'account_policies');
        Schema::dropIfExists($p.'recurring_journal_runs');
        Schema::dropIfExists($p.'recurring_journals');
        Schema::dropIfExists($p.'journal_templates');
    }
};
