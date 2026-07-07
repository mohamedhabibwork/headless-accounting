<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New first-class entities for advanced inventory operations:
 *
 *  - ha_batches                     : lot/batch master with manufacturing + expiration dates, GS1 AI
 *  - ha_serial_numbers              : one row per serial-tracked item with full status + assignment history
 *  - ha_goods_issues                : outbound issue (consumption, sampling, damage, write-off)
 *  - ha_stock_write_offs            : damaged/lost items with disposal workflow (pending → approved → disposed)
 *  - ha_disposal_orders             : disposal workflow header (links multiple write-offs)
 *  - ha_warehouse_reorder_rules     : per-warehouse reorder policies (overrides variant defaults)
 *  - ha_warehouse_prices            : per-warehouse pricing overrides
 *  - ha_inventory_reservations_audit: full audit of reservation lifecycle events
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

        // ---------------------------------------------------------------------------
        // Batches / Lots
        // ---------------------------------------------------------------------------
        Schema::create($p.'batches', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->string('batch_number', 64);
            $t->string('supplier_batch_number', 64)->nullable();
            $t->string('production_batch_number', 64)->nullable();
            $t->date('manufacturing_date')->nullable();
            $t->date('expiration_date')->nullable();
            $t->string('status')->default('active'); // enum: ['active', 'quarantined', 'expired', 'recalled', 'depleted']
            $t->json('attributes')->nullable();
            $t->text('notes')->nullable();
            $t->timestampsTz();
            $t->unique(['variant_id', 'batch_number']);
            $t->index(['variant_id', 'expiration_date']);
            $t->index(['status', 'expiration_date']);
        });

        Schema::create($p.'batch_stocks', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('batch_id')->constrained($p.'batches')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained($p.'locations')->cascadeOnDelete();
            $t->foreignId('bin_id')->nullable()->constrained($p.'warehouse_bins')->nullOnDelete();
            $t->integer('quantity')->default(0);
            $t->integer('reserved')->default(0);
            $t->char('currency', 3)->nullable();
            $t->decimal('unit_cost_minor', 18, 0)->nullable();
            $t->timestampsTz();
            $t->unique(['batch_id', 'location_id', 'bin_id']);
        });

        // ---------------------------------------------------------------------------
        // Serial numbers
        // ---------------------------------------------------------------------------
        Schema::create($p.'serial_numbers', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->foreignId('batch_id')->nullable()->constrained($p.'batches')->nullOnDelete();
            $t->string('serial', 128);
            $t->string('status')->default('in_stock'); // enum: ['in_stock', 'reserved', 'sold', 'in_transit', 'returned', 'under_repair', 'retired', 'lost']
            $t->foreignId('location_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->foreignId('bin_id')->nullable()->constrained($p.'warehouse_bins')->nullOnDelete();
            $t->date('manufacturing_date')->nullable();
            $t->date('warranty_expires_at')->nullable();
            $t->date('sold_at')->nullable();
            $t->foreignId('assigned_to_customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->json('warranty_terms')->nullable();
            $t->json('attributes')->nullable();
            $t->timestampsTz();
            $t->unique(['variant_id', 'serial']);
            $t->index(['status', 'variant_id']);
            $t->index('serial');
        });

        Schema::create($p.'serial_events', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('serial_number_id')->constrained($p.'serial_numbers')->cascadeOnDelete();
            $t->string('event', 32);                         // received, sold, returned, repaired, …
            $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32)->nullable();
            $t->foreignId('location_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestampTz('occurred_at');
            $t->timestampsTz();
            $t->index(['serial_number_id', 'occurred_at']);
        });

        // ---------------------------------------------------------------------------
        // Goods Issues
        // ---------------------------------------------------------------------------
        Schema::create($p.'goods_issues', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->string('number')->unique();
            $t->string('reason')->default('consumption'); // enum: ['sales', 'consumption', 'sampling', 'damage', 'loss', 'transfer', 'production', 'other']
            $t->date('issued_at');
            $t->string('state', 16)->default('draft');   // draft | approved | posted | cancelled
            $t->foreignId('cost_center_id')->nullable()->constrained($p.'cost_centers')->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained($p.'projects')->nullOnDelete();
            $t->json('lines');                           // [{variant_id, batch_id, bin_id, quantity, unit_cost_minor}]
            $t->text('notes')->nullable();
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['warehouse_id', 'state']);
        });

        // ---------------------------------------------------------------------------
        // Damaged / Lost / Disposal workflow
        // Note: disposal_orders must be created BEFORE stock_write_offs
        // because stock_write_offs has a FK to disposal_orders.
        // ---------------------------------------------------------------------------
        Schema::create($p.'disposal_orders', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->string('number')->unique();
            $t->string('method')->default('scrap'); // enum: ['scrap', 'recycle', 'return_to_vendor', 'donate', 'destroy', 'sell']
            $t->date('disposed_at')->nullable();
            $t->string('state', 16)->default('draft'); // draft | approved | executed | cancelled
            $t->text('reason')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'stock_write_offs', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->string('number')->unique();
            $t->string('category')->default('damaged'); // enum: ['damaged', 'lost', 'expired', 'obsolete', 'stolen', 'recalled']
            $t->date('occurred_at');
            $t->string('state', 16)->default('pending');   // pending | approved | disposed | cancelled
            $t->json('lines');                              // [{variant_id, batch_id, bin_id, quantity, unit_cost_minor}]
            $t->text('notes')->nullable();
            $t->foreignId('disposal_order_id')->nullable()->constrained($p.'disposal_orders')->nullOnDelete();
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['warehouse_id', 'state']);
        });

        // ---------------------------------------------------------------------------
        // Warehouse-specific reorder rules
        // ---------------------------------------------------------------------------
        Schema::create($p.'warehouse_reorder_rules', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained($p.'companies')->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained($p.'warehouses')->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->unsignedInteger('min_stock')->default(0);
            $t->unsignedInteger('max_stock')->default(0);
            $t->unsignedInteger('safety_stock')->default(0);
            $t->unsignedInteger('reorder_point')->default(0);
            $t->unsignedInteger('reorder_quantity')->default(0);
            $t->unsignedInteger('lead_time_days')->default(0);
            $t->boolean('automatic_replenishment')->default(false);
            $t->string('preferred_vendor_code', 64)->nullable();
            $t->timestampsTz();
            $t->unique(['warehouse_id', 'variant_id']);
        });

        // ---------------------------------------------------------------------------
        // Warehouse-specific pricing overrides
        // ---------------------------------------------------------------------------
        Schema::create($p.'warehouse_prices', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('warehouse_id')->constrained($p.'warehouses')->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->char('currency', 3);
            $t->decimal('amount_minor', 18, 0);
            $t->decimal('min_quantity', 10, 2)->default(1);
            $t->boolean('tax_inclusive')->default(false);
            $t->date('effective_from')->nullable();
            $t->date('effective_until')->nullable();
            $t->timestampsTz();
            $t->index(['warehouse_id', 'variant_id', 'currency']);
        });

        // ---------------------------------------------------------------------------
        // Reservation audit (lifecycle events for stock_reservations)
        // ---------------------------------------------------------------------------
        Schema::create($p.'reservation_events', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('stock_reservation_id')->constrained($p.'stock_reservations')->cascadeOnDelete();
            $t->string('event', 32); // created, released, expired, fulfilled
            $t->integer('quantity_delta');
            $t->text('note')->nullable();
            $t->timestampTz('occurred_at');
            $t->timestampsTz();
            $t->index(['stock_reservation_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'reservation_events');
        Schema::dropIfExists($p.'warehouse_prices');
        Schema::dropIfExists($p.'warehouse_reorder_rules');
        Schema::dropIfExists($p.'disposal_orders');
        Schema::dropIfExists($p.'stock_write_offs');
        Schema::dropIfExists($p.'goods_issues');
        Schema::dropIfExists($p.'serial_events');
        Schema::dropIfExists($p.'serial_numbers');
        Schema::dropIfExists($p.'batch_stocks');
        Schema::dropIfExists($p.'batches');
    }
};
