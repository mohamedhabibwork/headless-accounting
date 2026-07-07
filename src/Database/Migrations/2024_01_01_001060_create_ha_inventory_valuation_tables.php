<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CostLayer — per-variant cost bucket for inventory valuation.
 * Each receipt of stock creates one layer; issues consume layers
 * according to the chosen method (FIFO, LIFO, WA, Standard).
 *
 * For Standard Cost we keep a single layer per variant whose
 * `unit_cost_minor` is updated by `StandardCostRecalculator`.
 *
 * For Weighted Average we keep a single running average (also a
 * CostLayer row) that's recomputed on every receipt.
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
        Schema::create($p.'cost_layers', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained($p.'locations')->cascadeOnDelete();
            $t->date('received_at');
            $t->unsignedInteger('quantity_received');
            $t->unsignedInteger('quantity_remaining');                  // for FIFO/LIFO
            $t->bigInteger('unit_cost_minor');                          // cost per unit, minor units
            $t->char('currency', 3);
            $t->string('source'); // enum: ['gr', 'adjust', 'transfer', 'return']
            $t->string('source_document_type')->nullable();
            $t->unsignedBigInteger('source_document_id')->nullable();
            $t->index(['source_document_type', 'source_document_id']);
            $t->timestampsTz();
            $t->index(['variant_id', 'location_id', 'received_at']);
        });

        Schema::create($p.'standard_costs', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->char('currency', 3);
            $t->bigInteger('unit_cost_minor');
            $t->decimal('variance_pct', 8, 4)->default(0);
            $t->date('effective_from');
            $t->timestampsTz();
            $t->index(['variant_id', 'effective_from']);
        });

        Schema::create($p.'inventory_transfers', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->foreignId('from_location_id')->constrained($p.'locations');
            $t->foreignId('to_location_id')->constrained($p.'locations');
            $t->date('transferred_at');
            $t->string('state', 16)->default('draft');
            $t->json('lines');
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'inventory_adjustments', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->foreignId('location_id')->constrained($p.'locations');
            $t->date('adjusted_at');
            $t->string('reason', 64);                              // 'count', 'obsolete', 'damaged', 'scrap'
            $t->json('lines');
            $t->text('notes')->nullable();
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'boms', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained($p.'products')->cascadeOnDelete();
            $t->string('code', 64);
            $t->string('name');
            $t->unsignedInteger('quantity_per_unit')->default(1);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'bom_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('bom_id')->constrained($p.'boms')->cascadeOnDelete();
            $t->foreignId('component_id')->nullable()->constrained($p.'products')->nullOnDelete();
            $t->unsignedInteger('quantity');
            $t->decimal('scrap_pct', 8, 4)->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'production_orders', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->foreignId('bom_id')->nullable()->constrained($p.'boms')->nullOnDelete();
            $t->unsignedInteger('quantity_to_produce');
            $t->date('scheduled_date');
            $t->string('state', 16)->default('planned');
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'bom_lines');
        Schema::dropIfExists($p.'boms');
        Schema::dropIfExists($p.'production_orders');
        Schema::dropIfExists($p.'inventory_adjustments');
        Schema::dropIfExists($p.'inventory_transfers');
        Schema::dropIfExists($p.'standard_costs');
        Schema::dropIfExists($p.'cost_layers');
    }
};
