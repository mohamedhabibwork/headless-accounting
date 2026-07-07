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
        Schema::create($p.'locations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->string('type')->default('warehouse'); // enum: ['warehouse', 'store', 'dropship', 'transit']
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'stock_items', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained($p.'locations')->cascadeOnDelete();
            $t->integer('on_hand')->default(0);
            $t->integer('reserved')->default(0);
            $t->integer('incoming')->default(0);
            $t->decimal('average_cost_minor', 18, 0)->nullable();
            $t->char('currency', 3)->nullable();
            $t->timestampsTz();
            $t->unique(['variant_id', 'location_id']);
        });

        Schema::create($p.'stock_movements', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('stock_item_id')->constrained($p.'stock_items')->cascadeOnDelete();
            $t->string('reason', 64);            // receive, pick, adjust, transfer-out, etc.
            $t->integer('quantity');
            $t->integer('balance_after');
            $t->morphs('source');               // polymorphic: order, shipment, etc.
            $t->timestampTz('occurred_at');
            $t->timestampsTz();
        });

        Schema::create($p.'stock_reservations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('stock_item_id')->constrained($p.'stock_items')->cascadeOnDelete();
            $t->morphs('source');               // cart, order, draft
            $t->integer('quantity');
            $t->timestampTz('expires_at')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'stock_reservations');
        Schema::dropIfExists($p.'stock_movements');
        Schema::dropIfExists($p.'stock_items');
        Schema::dropIfExists($p.'locations');
    }
};
