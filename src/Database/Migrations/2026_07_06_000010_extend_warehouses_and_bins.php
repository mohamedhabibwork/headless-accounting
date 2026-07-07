<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the warehouse + location structure with:
 *  - parent_id (warehouse hierarchy)
 *  - branch_id (link a warehouse to a Branch tenant row)
 *  - inter_company flag (warehouse belongs to a different company)
 *  - new warehouse types: consignment, virtual, quarantine, returns, customer, three_pl
 *  - new zone kind: cross_dock, returns, quarantine already exist
 *  - structured bin hierarchy: aisle, rack, shelf, level, position (in addition to free-text code)
 *  - capacity enforcement fields (current_utilisation_units)
 *  - bin QR code + multi-barcode index
 *
 * `warehouses.type` and `warehouse_zones.kind` are plain string columns;
 * the full set of allowed values is enforced at the application layer.
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

        Schema::table($p.'warehouses', function (Blueprint $t) use ($p) {
            $t->foreignId('parent_id')->nullable()->after('location_id')
                ->constrained($p.'warehouses')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->after('parent_id')
                ->constrained($p.'branches')->nullOnDelete();
            $t->foreignId('owner_company_id')->nullable()->after('branch_id')
                ->constrained($p.'companies')->nullOnDelete();
            $t->boolean('inter_company')->default(false)->after('owner_company_id');
            $t->boolean('consignment')->default(false)->after('inter_company');
            $t->boolean('virtual')->default(false)->after('consignment');
            $t->boolean('in_transit')->default(false)->after('virtual');
            $t->boolean('quarantine_only')->default(false)->after('in_transit');
            $t->boolean('returns_only')->default(false)->after('quarantine_only');
            $t->index(['parent_id']);
            $t->index(['branch_id']);
        });

        Schema::table($p.'warehouse_bins', function (Blueprint $t) {
            $t->string('aisle', 16)->nullable()->after('zone_id');
            $t->string('rack', 16)->nullable()->after('aisle');
            $t->string('shelf', 16)->nullable()->after('rack');
            $t->string('level', 16)->nullable()->after('shelf');
            $t->string('position', 16)->nullable()->after('level');
            $t->string('qr_code', 128)->nullable()->after('barcode');
            $t->decimal('current_units', 12, 2)->default(0)->after('capacity_units');
            $t->decimal('current_weight_grams', 12, 2)->default(0)->after('max_weight_grams');
            $t->index('qr_code');
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::table($p.'warehouse_bins', function (Blueprint $t) {
            $t->dropIndex(['qr_code']);
            $t->dropColumn(['aisle', 'rack', 'shelf', 'level', 'position', 'qr_code', 'current_units', 'current_weight_grams']);
        });
        Schema::table($p.'warehouses', function (Blueprint $t) {
            $t->dropForeign(['parent_id']);
            $t->dropForeign(['branch_id']);
            $t->dropForeign(['owner_company_id']);
            $t->dropIndex(['parent_id']);
            $t->dropIndex(['branch_id']);
            $t->dropColumn(['parent_id', 'branch_id', 'owner_company_id', 'inter_company', 'consignment', 'virtual', 'in_transit', 'quarantine_only', 'returns_only']);
        });
    }
};
