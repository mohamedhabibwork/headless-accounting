<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich the shipments table for multi-warehouse fulfillment.
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

        Schema::table($p.'shipments', function (Blueprint $t) use ($p) {
            $t->foreignId('fulfillment_plan_id')->nullable()->after('order_id')->constrained($p.'fulfillment_plans')->nullOnDelete();
            $t->foreignId('pick_list_id')->nullable()->after('fulfillment_plan_id')->constrained($p.'pick_lists')->nullOnDelete();
            $t->foreignId('pack_station_id')->nullable()->after('pick_list_id')->constrained($p.'pack_stations')->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->after('pack_station_id')->constrained($p.'warehouses')->nullOnDelete();
            $t->foreignId('carrier_id')->nullable()->after('warehouse_id')->constrained($p.'carriers')->nullOnDelete();
            $t->foreignId('shipping_rate_card_id')->nullable()->after('carrier_id')->constrained($p.'shipping_rate_cards')->nullOnDelete();
            $t->string('carrier_code', 32)->nullable()->after('carrier_id');
            $t->string('service_code', 32)->nullable()->after('carrier_code');
            $t->string('tracking_url')->nullable()->after('tracking_number');
            $t->decimal('length_mm', 12, 2)->nullable()->after('weight_grams');
            $t->decimal('width_mm', 12, 2)->nullable()->after('length_mm');
            $t->decimal('height_mm', 12, 2)->nullable()->after('width_mm');
            $t->string('label_url')->nullable()->after('customs');
            $t->json('metadata')->nullable()->after('label_url');
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::table($p.'shipments', function (Blueprint $t) {
            $t->dropForeign(['fulfillment_plan_id']);
            $t->dropForeign(['pick_list_id']);
            $t->dropForeign(['pack_station_id']);
            $t->dropForeign(['warehouse_id']);
            $t->dropForeign(['carrier_id']);
            $t->dropForeign(['shipping_rate_card_id']);
            $t->dropColumn([
                'fulfillment_plan_id', 'pick_list_id', 'pack_station_id',
                'warehouse_id', 'carrier_id', 'shipping_rate_card_id',
                'carrier_code', 'service_code',
                'tracking_url', 'length_mm', 'width_mm', 'height_mm',
                'label_url', 'metadata',
            ]);
        });
    }
};
