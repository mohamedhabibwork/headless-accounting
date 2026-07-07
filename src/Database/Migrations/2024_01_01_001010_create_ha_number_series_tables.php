<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NumberSeries = per-company prefix counters that drive document
 * numbering. Configurable per prefix, with a year reset and pad length.
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
        Schema::create($p.'number_series', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->string('prefix', 16);                // 'INV', 'ORD', 'JE', 'BILL' …
            $t->string('description', 128)->nullable();
            $t->unsignedInteger('next_number')->default(1);
            $t->unsignedSmallInteger('pad_length')->default(6);
            $t->string('separator', 4)->default('-'); // INV-2026-000123
            $t->boolean('include_year')->default(true);
            $t->boolean('reset_yearly')->default(true);
            $t->year('last_reset_year')->nullable();
            $t->json('config')->nullable();
            $t->timestampsTz();
            $t->unique(['company_id', 'prefix']);
        });

        Schema::create($p.'cost_allocations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->morphs('source');                          // order, invoice, expense, payroll
            $t->foreignId('cost_center_id')->constrained($p.'cost_centers')->cascadeOnDelete();
            $t->foreignId('profit_center_id')->nullable()->constrained($p.'profit_centers')->nullOnDelete();
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->decimal('percentage', 5, 2)->nullable();
            $t->timestampTz('allocated_at');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'cost_allocations');
        Schema::dropIfExists($p.'number_series');
    }
};
