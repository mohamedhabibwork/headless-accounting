<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-warehouse fulfillment + stocktaking system.
 *
 * Introduces:
 *  - warehouses (richer entity than ha_locations: holds address,
 *    fulfillment capabilities, default pick/pack zones)
 *  - warehouse_zones (receiving, storage, pick-face, packing, shipping)
 *  - warehouse_bins (specific shelf/bin within a zone)
 *  - carriers (DHL, UPS, FedEx, …)
 *  - shipping_rate_cards (per-carrier service × zone × weight tier)
 *  - fulfillment_plans (per-order routing: which warehouse ships which line)
 *  - pick_lists (per-warehouse pick waves; assigns work to pickers)
 *  - pick_list_lines (per pick list: variant + bin + quantity)
 *  - pack_stations (packing records)
 *  - stocktakes (count headers with state machine)
 *  - stocktake_lines (per-variant counts and variances)
 *  - stocktake_variances (resolved differences posted as adjustments)
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

        Schema::create($p.'warehouses', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->string('type')->default('warehouse'); // enum: ['warehouse', 'store', 'dropship', 'transit', 'pop_up']
            $t->boolean('fulfillment_enabled')->default(true);
            $t->boolean('stocktake_enabled')->default(true);
            $t->boolean('is_default')->default(false);
            $t->unsignedSmallInteger('priority')->default(100);    // lower = preferred for allocation
            $t->json('shipping_address')->nullable();              // warehouse physical address
            $t->json('contact')->nullable();                       // phone, email, manager
            $t->json('capabilities')->nullable();                  // {hazmat:false, cold_chain:true, oversized:false}
            $t->json('opening_hours')->nullable();                 // per weekday
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('timezone', 64)->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->index(['company_id', 'active']);
        });

        Schema::create($p.'warehouse_zones', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('warehouse_id')->constrained($p.'warehouses')->cascadeOnDelete();
            $t->string('code', 32);                  // e.g. RECV, BULK, PICK-A, PACK, SHIP
            $t->string('name');
            $t->string('kind')->default('storage'); // enum: ['receiving', 'storage', 'pick_face', 'packing', 'shipping', 'quarantine', 'returns']
            $t->boolean('is_default_pick')->default(false);
            $t->boolean('is_default_pack')->default(false);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestampsTz();
            $t->unique(['warehouse_id', 'code']);
        });

        Schema::create($p.'warehouse_bins', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('zone_id')->constrained($p.'warehouse_zones')->cascadeOnDelete();
            $t->string('code', 64);                  // e.g. A-01-03
            $t->string('barcode', 64)->nullable();
            $t->decimal('capacity_units', 12, 2)->nullable();
            $t->decimal('max_weight_grams', 12, 2)->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['zone_id', 'code']);
            $t->index('barcode');
        });

        Schema::create($p.'carriers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();        // dhl, ups, fedex, gls, dpd, …
            $t->string('name');
            $t->string('tracking_url_template')->nullable();
            $t->json('service_levels')->nullable();   // [{code,name,eta_days_from,eta_days_to}]
            $t->json('credentials')->nullable();      // api_key, account_number — encrypted at rest by app
            $t->boolean('sandbox')->default(true);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'shipping_rate_cards', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('carrier_id')->constrained($p.'carriers')->cascadeOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained($p.'warehouses')->nullOnDelete();
            $t->string('service_code', 32);                    // e.g. 'express', 'economy'
            $t->string('service_name');
            $t->json('destinations');                          // ['FR','DE','*', …] or postcode ranges
            $t->decimal('min_weight_grams', 12, 2)->default(0);
            $t->decimal('max_weight_grams', 12, 2)->nullable();
            $t->decimal('base_cost_minor', 18, 0)->default(0);
            $t->decimal('per_kg_cost_minor', 18, 0)->default(0);
            $t->char('currency', 3);
            $t->decimal('free_shipping_threshold_minor', 18, 0)->nullable();
            $t->unsignedSmallInteger('eta_days_from')->default(1);
            $t->unsignedSmallInteger('eta_days_to')->default(3);
            $t->unsignedSmallInteger('priority')->default(100);   // lower = preferred
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->index(['carrier_id', 'service_code', 'active']);
        });

        Schema::create($p.'fulfillment_plans', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('order_id')->constrained($p.'orders')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->string('strategy', 32)->default('cheapest');   // cheapest | fastest | closest | priority | manual
            $t->string('state', 16)->default('planned');       // planned | allocating | allocated | picking | packed | shipped | delivered | cancelled | partial
            $t->json('allocations')->nullable();                // [{warehouse_id, variant_id, quantity}]
            $t->json('shipping_options')->nullable();           // ranked carriers
            $t->json('metadata')->nullable();
            $t->timestampTz('planned_at')->nullable();
            $t->timestampTz('allocated_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();
            $t->index(['order_id', 'state']);
        });

        Schema::create($p.'pick_lists', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('fulfillment_plan_id')->constrained($p.'fulfillment_plans')->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained($p.'warehouses')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->string('state', 16)->default('open');          // open | assigned | picking | picked | packed | cancelled
            $t->string('picker_name')->nullable();
            $t->json('routes')->nullable();                    // optimized pick route through bins
            $t->timestampTz('assigned_at')->nullable();
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();
            $t->index(['warehouse_id', 'state']);
        });

        Schema::create($p.'pick_list_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('pick_list_id')->constrained($p.'pick_lists')->cascadeOnDelete();
            $t->foreignId('bin_id')->nullable()->constrained($p.'warehouse_bins')->nullOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->foreignId('stock_item_id')->nullable()->constrained($p.'stock_items')->nullOnDelete();
            $t->unsignedInteger('quantity_requested');
            $t->unsignedInteger('quantity_picked')->default(0);
            $t->string('state', 16)->default('pending');        // pending | picked | short | skipped
            $t->text('note')->nullable();
            $t->unsignedSmallInteger('pick_sequence')->default(0);
            $t->timestampTz('picked_at')->nullable();
            $t->timestampsTz();
            $t->index(['pick_list_id', 'state']);
        });

        Schema::create($p.'pack_stations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('pick_list_id')->constrained($p.'pick_lists')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->string('packer_name')->nullable();
            $t->string('carton_type', 32)->nullable();
            $t->decimal('weight_grams', 12, 2)->nullable();
            $t->decimal('length_mm', 12, 2)->nullable();
            $t->decimal('width_mm', 12, 2)->nullable();
            $t->decimal('height_mm', 12, 2)->nullable();
            $t->json('items')->nullable();                     // [{variant_id, quantity, picked_quantity}]
            $t->string('state', 16)->default('open');          // open | packed | sealed | shipped
            $t->timestampTz('packed_at')->nullable();
            $t->timestampTz('sealed_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'stocktakes', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained($p.'warehouses')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->string('state', 16)->default('draft');        // draft|counting|counted|under_review|approved|posted|cancelled
            $t->string('scope')->default('full'); // enum: ['full', 'cycle', 'zone', 'variant']
            $t->date('scheduled_at')->nullable();
            $t->date('counted_at')->nullable();
            $t->date('approved_at')->nullable();
            $t->date('posted_at')->nullable();
            $t->json('zones')->nullable();                     // restricted scope (zone ids)
            $t->json('variants')->nullable();                  // restricted scope (variant ids)
            $t->json('counters')->nullable();                  // [{name, assigned_at, completed_at}]
            $t->text('notes')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->foreignId('posted_journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['warehouse_id', 'state']);
        });

        Schema::create($p.'stocktake_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('stocktake_id')->constrained($p.'stocktakes')->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->foreignId('bin_id')->nullable()->constrained($p.'warehouse_bins')->nullOnDelete();
            $t->unsignedInteger('system_quantity')->default(0);
            $t->unsignedInteger('counted_quantity')->nullable();
            $t->integer('variance')->default(0);               // counted - system (signed)
            $t->decimal('unit_cost_minor', 18, 0)->nullable();
            $t->decimal('variance_value_minor', 18, 0)->default(0);
            $t->char('currency', 3)->nullable();
            $t->string('state', 16)->default('pending');       // pending|counted|recount|approved|rejected
            $t->unsignedTinyInteger('count_round')->default(1);
            $t->text('reason')->nullable();                    // damage, theft, miscount, found, …
            $t->foreignId('counter_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->timestampTz('counted_at')->nullable();
            $t->timestampsTz();
            $t->unique(['stocktake_id', 'variant_id', 'bin_id', 'count_round']);
            $t->index(['stocktake_id', 'state']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'stocktake_lines');
        Schema::dropIfExists($p.'stocktakes');
        Schema::dropIfExists($p.'pack_stations');
        Schema::dropIfExists($p.'pick_list_lines');
        Schema::dropIfExists($p.'pick_lists');
        Schema::dropIfExists($p.'fulfillment_plans');
        Schema::dropIfExists($p.'shipping_rate_cards');
        Schema::dropIfExists($p.'carriers');
        Schema::dropIfExists($p.'warehouse_bins');
        Schema::dropIfExists($p.'warehouse_zones');
        Schema::dropIfExists($p.'warehouses');
    }
};
