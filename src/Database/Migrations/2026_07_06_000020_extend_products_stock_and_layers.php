<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the product catalog and stock item buckets with:
 *  - product.item_type  (raw_material, finished_good, semi_finished, service, consumable, spare_part, kit)
 *  - product_variant.batch_tracked, serial_tracked, expiration_tracked
 *  - product_variant.unit_of_measure, hazard_class, temperature_class
 *  - product_variant.reorder settings (min/max/safety/reorder_point/eoq)
 *  - stock_item.batch_id, serial_number (high-water-mark column for fast filters)
 *  - cost_layer.batch_id, expiration_date, manufacturing_date
 *  - stock_movement.reason (kept as varchar — enforced at app level)
 *  - stock_reservation.batch_id, expiration_date, allocation_priority
 *  - stock_item.bin_id (bin ↔ stock linkage)
 *  - a new ha_product_barcodes table for multi-barcode-per-variant (GS1)
 *
 * `cost_layers.source` is a plain string column; the allowed values are
 * enforced at the application layer.
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

        Schema::table($p.'products', function (Blueprint $t) {
            $t->string('item_type', 32)->default('finished_good')->after('stock_tracked');
            $t->boolean('batch_tracked')->default(false)->after('item_type');
            $t->boolean('serial_tracked')->default(false)->after('batch_tracked');
            $t->boolean('expiration_tracked')->default(false)->after('serial_tracked');
            $t->string('unit_of_measure', 16)->default('pcs')->after('expiration_tracked');
            $t->string('hazard_class', 32)->nullable()->after('unit_of_measure');
            $t->string('temperature_class', 32)->nullable()->after('hazard_class');
            $t->index('item_type');
        });

        Schema::table($p.'product_variants', function (Blueprint $t) {
            $t->string('unit_of_measure', 16)->default('pcs')->after('stock_tracked');
            $t->boolean('batch_tracked')->default(false)->after('unit_of_measure');
            $t->boolean('serial_tracked')->default(false)->after('batch_tracked');
            $t->boolean('expiration_tracked')->default(false)->after('serial_tracked');
            $t->string('gs1_gtin', 32)->nullable()->after('serial_tracked');
            $t->string('hazard_class', 32)->nullable()->after('gs1_gtin');
            $t->string('temperature_class', 32)->nullable()->after('hazard_class');
            $t->unsignedInteger('min_stock')->default(0)->after('temperature_class');
            $t->unsignedInteger('max_stock')->default(0)->after('min_stock');
            $t->unsignedInteger('safety_stock')->default(0)->after('max_stock');
            $t->unsignedInteger('reorder_point')->default(0)->after('safety_stock');
            $t->unsignedInteger('reorder_quantity')->default(0)->after('reorder_point');
            $t->unsignedInteger('lead_time_days')->default(0)->after('reorder_quantity');
            $t->index('gs1_gtin');
        });

        // Multi-barcode table for a variant (EAN-13, UPC-A, GS1-128, etc.)
        Schema::create($p.'product_barcodes', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->string('barcode', 64);
            $t->string('symbology', 16)->default('EAN13'); // EAN13, UPC_A, GS1_128, CODE128, QR
            $t->boolean('is_primary')->default(false);
            $t->string('label_template', 64)->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['barcode', 'symbology']);
            $t->index(['variant_id', 'active']);
        });

        // Stock item ↔ Bin linkage (so we can ask "which bins hold variant X at warehouse Y")
        Schema::table($p.'stock_items', function (Blueprint $t) use ($p) {
            $t->foreignId('bin_id')->nullable()->after('location_id')
                ->constrained($p.'warehouse_bins')->nullOnDelete();
            $t->unsignedInteger('min_stock')->default(0)->after('incoming');
            $t->unsignedInteger('max_stock')->default(0)->after('min_stock');
            $t->unsignedInteger('reorder_point')->default(0)->after('max_stock');
            $t->index(['bin_id']);
        });

        // Cost layers gain batch + expiration dates
        Schema::table($p.'cost_layers', function (Blueprint $t) {
            $t->date('manufacturing_date')->nullable()->after('received_at');
            $t->date('expiration_date')->nullable()->after('manufacturing_date');
            $t->string('batch_number', 64)->nullable()->after('expiration_date');
            $t->index(['variant_id', 'expiration_date']);
            $t->index('batch_number');
        });

        // Reservation gains batch + priority.
        // The original migration already created an index on
        // [source_type, source_id] via $t->morphs('source'); we don't
        // re-add it here.
        Schema::table($p.'stock_reservations', function (Blueprint $t) {
            $t->string('batch_number', 64)->nullable()->after('expires_at');
            $t->string('serial_number', 128)->nullable()->after('batch_number');
            $t->date('expiration_date')->nullable()->after('serial_number');
            $t->unsignedSmallInteger('priority')->default(100)->after('expiration_date');
        });
    }

    public function down(): void
    {
        $p = $this->prefix();

        Schema::table($p.'stock_reservations', function (Blueprint $t) {
            $t->dropColumn(['batch_number', 'serial_number', 'expiration_date', 'priority']);
        });

        Schema::table($p.'cost_layers', function (Blueprint $t) {
            $t->dropIndex(['variant_id', 'expiration_date']);
            $t->dropIndex(['batch_number']);
            $t->dropColumn(['manufacturing_date', 'expiration_date', 'batch_number']);
        });

        Schema::table($p.'stock_items', function (Blueprint $t) {
            $t->dropForeign(['bin_id']);
            $t->dropIndex(['bin_id']);
            $t->dropColumn(['bin_id', 'min_stock', 'max_stock', 'reorder_point']);
        });

        Schema::dropIfExists($p.'product_barcodes');

        Schema::table($p.'product_variants', function (Blueprint $t) {
            $t->dropIndex(['gs1_gtin']);
            $t->dropColumn([
                'unit_of_measure', 'batch_tracked', 'serial_tracked', 'expiration_tracked',
                'gs1_gtin', 'hazard_class', 'temperature_class',
                'min_stock', 'max_stock', 'safety_stock', 'reorder_point', 'reorder_quantity', 'lead_time_days',
            ]);
        });

        Schema::table($p.'products', function (Blueprint $t) {
            $t->dropIndex(['item_type']);
            $t->dropColumn([
                'item_type', 'batch_tracked', 'serial_tracked', 'expiration_tracked',
                'unit_of_measure', 'hazard_class', 'temperature_class',
            ]);
        });
    }
};
