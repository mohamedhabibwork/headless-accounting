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
        Schema::create($p.'accounts', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();        // e.g. '1200'
            $t->string('name');
            $t->string('type'); // enum: ['asset', 'liability', 'equity', 'revenue', 'expense']
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->char('currency', 3)->nullable();     // null = multi-currency
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->index('parent_id');
        });

        Schema::create($p.'fiscal_years', function (Blueprint $t) {
            $t->id();
            $t->string('name', 32);
            $t->date('starts_at');
            $t->date('ends_at');
            $t->boolean('closed')->default(false);
            $t->timestampsTz();
        });

        Schema::create($p.'accounting_periods', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('fiscal_year_id')->constrained($p.'fiscal_years')->cascadeOnDelete();
            $t->string('code', 32);                 // '2026-04'
            $t->date('starts_at');
            $t->date('ends_at');
            $t->boolean('closed')->default(false);
            $t->timestampsTz();
        });

        Schema::create($p.'journal_entries', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('number', 32)->unique();
            $t->morphs('source');                    // order, payment, refund, inventory
            $t->foreignId('period_id')->nullable()->constrained($p.'accounting_periods')->nullOnDelete();
            $t->char('currency', 3);
            $t->date('posted_at');
            $t->text('description')->nullable();
            $t->boolean('auto_posted')->default(false);
            $t->timestampsTz();
        });

        Schema::create($p.'postings', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('journal_entry_id')->constrained($p.'journal_entries')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained($p.'accounts')->restrictOnDelete();
            $t->decimal('debit_minor', 18, 0)->default(0);
            $t->decimal('credit_minor', 18, 0)->default(0);
            $t->char('currency', 3);
            $t->text('memo')->nullable();
            $t->timestampsTz();
            $t->index(['account_id']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'postings');
        Schema::dropIfExists($p.'journal_entries');
        Schema::dropIfExists($p.'accounting_periods');
        Schema::dropIfExists($p.'fiscal_years');
        Schema::dropIfExists($p.'accounts');
    }
};
