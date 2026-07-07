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
        Schema::create($p.'aut_recurring_rules', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('kind'); // enum: ['invoice', 'bill', 'journal', 'subscription']
            $t->string('frequency')->default('month'); // enum: ['day', 'week', 'month', 'quarter', 'year']
            $t->unsignedTinyInteger('day_of_month')->nullable();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->date('next_run_at')->nullable();
            $t->date('last_run_at')->nullable();
            $t->unsignedInteger('max_runs')->nullable();
            $t->unsignedInteger('runs_count')->default(0);
            $t->json('template');                                  // driver-specific payload
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'aut_recurring_runs', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('rule_id')->constrained($p.'aut_recurring_rules')->cascadeOnDelete();
            $t->timestampTz('run_at');
            $t->string('status', 16)->default('pending');           // pending | posted | failed | skipped
            $t->string('reference_id', 64)->nullable();
            $t->text('error')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'aut_scheduled_reports', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('report_code', 64);
            $t->json('filters')->nullable();
            $t->string('frequency'); // enum: ['day', 'week', 'month', 'quarter']
            $t->json('recipients');                              // list of emails
            $t->string('format')->default('csv'); // enum: ['csv', 'pdf', 'json']
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'aut_email_templates', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->string('code', 64);                              // 'invoice.paid', 'payment.failed', …
            $t->string('subject');
            $t->text('body');
            $t->json('placeholders')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'aut_auto_numberings', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('prefix', 16);
            $t->string('target_model', 64);                     // 'App\\Models\\Invoice' style — or 'ha_invoices'
            $t->unsignedInteger('next_number')->default(1);
            $t->unsignedSmallInteger('pad')->default(6);
            $t->string('separator', 4)->default('-');
            $t->boolean('reset_yearly')->default(true);
            $t->timestampsTz();
            $t->unique(['company_id', 'prefix', 'target_model']);
        });

        // Documents
        Schema::create($p.'doc_attachments', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->morphs('subject');                                  // invoice, bill, journal, payment, asset, …
            $t->string('filename', 191);
            $t->string('mime_type', 64);
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->string('storage_disk', 64)->default('local');
            $t->string('storage_path', 255);
            $t->string('checksum_sha256', 64)->nullable();
            $t->boolean('ocr_processed')->default(false);
            $t->json('ocr_result')->nullable();
            $t->json('extra_metadata')->nullable();
            $t->boolean('requires_signature')->default(false);
            $t->timestampTz('signed_at')->nullable();
            $t->string('signed_by', 128)->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'doc_versions', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('attachment_id')->constrained($p.'doc_attachments')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('storage_path', 255);
            $t->string('checksum_sha256', 64)->nullable();
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->morphs('uploader');
            $t->text('comment')->nullable();
            $t->timestampsTz();
            $t->unique(['attachment_id', 'version']);
        });

        // Multi-currency extras
        Schema::create($p.'realized_gain_losses', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained($p.'payments')->nullOnDelete();
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');                     // positive = gain, negative = loss
            $t->decimal('fx_rate_at_booking', 18, 8)->nullable();
            $t->decimal('fx_rate_at_payment', 18, 8)->nullable();
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'currency_revaluations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->char('currency', 3);
            $t->date('as_of');
            $t->json('breakdown');                                // { account_id: amount_minor, ... }
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
            $t->unique(['company_id', 'currency', 'as_of']);
        });

        // SaaS plans / quotas
        Schema::create($p.'saas_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->decimal('price_monthly', 14, 2)->default(0);
            $t->json('features')->nullable();                   // list of enabled modules
            $t->json('limits')->nullable();                      // { 'orders_per_month': 5000, 'users': 25, ... }
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'saas_subscriptions', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('plan_id')->constrained($p.'saas_plans');
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->date('started_at');
            $t->date('renews_at')->nullable();
            $t->date('trial_ends_at')->nullable();
            $t->string('state', 16)->default('active');           // trial | active | past_due | cancelled
            $t->json('modules_enabled')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'saas_usage_counters', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('subscription_id')->constrained($p.'saas_subscriptions')->cascadeOnDelete();
            $t->string('metric_key', 64);                        // 'orders_count', 'storage_gb'
            $t->date('period');                                  // YYYY-MM-01
            $t->unsignedBigInteger('count')->default(0);
            $t->timestampsTz();
            $t->unique(['subscription_id', 'metric_key', 'period']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'saas_usage_counters');
        Schema::dropIfExists($p.'saas_subscriptions');
        Schema::dropIfExists($p.'saas_plans');
        Schema::dropIfExists($p.'currency_revaluations');
        Schema::dropIfExists($p.'realized_gain_losses');
        Schema::dropIfExists($p.'doc_versions');
        Schema::dropIfExists($p.'doc_attachments');
        Schema::dropIfExists($p.'aut_auto_numberings');
        Schema::dropIfExists($p.'aut_email_templates');
        Schema::dropIfExists($p.'aut_scheduled_reports');
        Schema::dropIfExists($p.'aut_recurring_runs');
        Schema::dropIfExists($p.'aut_recurring_rules');
    }
};
