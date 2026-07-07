<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reporting + integrators + audit extensions.
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
        // Reporting cache
        Schema::create('rep_snapshot_cache', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->string('report_code', 64);
            $t->string('params_hash', 64);                       // md5 of canonicalized params
            $t->json('payload');
            $t->timestampTz('expires_at')->nullable();
            $t->timestampsTz();
            $t->unique(['company_id', 'report_code', 'params_hash']);
        });

        Schema::create('rep_dashboard_widgets', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->morphs('owner');                                  // employee, user, role
            $t->string('name', 64);
            $t->string('metric', 64);
            $t->json('config')->nullable();
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestampsTz();
        });

        // Integrations & Webhooks
        Schema::create('int_webhooks', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('name');
            $t->string('url', 255);
            $t->string('secret', 64);
            $t->json('event_types');                              // ['order.created', ...]
            $t->string('content_type', 32)->default('application/json');
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create('int_webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('webhook_id')->constrained('int_webhooks')->cascadeOnDelete();
            $t->string('event_type', 64);
            $t->unsignedInteger('http_status')->nullable();
            $t->json('payload');
            $t->unsignedSmallInteger('attempt')->default(1);
            $t->text('error')->nullable();
            $t->timestampTz('delivered_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create('int_api_clients', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('name', 64);
            $t->string('client_id', 32)->unique();
            $t->string('secret_hash', 255);
            $t->json('scopes')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampTz('last_used_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create('int_audit_login_history', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->morphs('subject');
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 256)->nullable();
            $t->boolean('success')->default(true);
            $t->text('reason')->nullable();
            $t->timestampTz('logged_in_at');
            $t->timestampsTz();
        });

        Schema::create('int_audit_export_log', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->morphs('actor');
            $t->string('report', 64);
            $t->string('format', 16);
            $t->json('filters')->nullable();
            $t->unsignedInteger('row_count')->default(0);
            $t->string('ip', 45)->nullable();
            $t->timestampsTz();
        });

        Schema::create('int_change_history', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->morphs('subject');
            $t->morphs('actor');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('event', 64);
            $t->string('reason')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_change_history');
        Schema::dropIfExists('int_audit_export_log');
        Schema::dropIfExists('int_audit_login_history');
        Schema::dropIfExists('int_api_clients');
        Schema::dropIfExists('int_webhook_deliveries');
        Schema::dropIfExists('int_webhooks');
        Schema::dropIfExists('rep_dashboard_widgets');
        Schema::dropIfExists('rep_snapshot_cache');
    }
};
