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
        Schema::create($p.'carts', function (Blueprint $t) use ($p) {
            $t->id();
            $t->uuid('token')->unique();
            $t->foreignId('customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->string('channel_code', 32);
            $t->char('currency', 3);
            $t->string('locale', 8);
            $t->json('metadata')->nullable();
            $t->timestampTz('expires_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'cart_items', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('cart_id')->constrained($p.'carts')->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->unsignedInteger('quantity');
            $t->bigInteger('unit_price_minor');
            $t->char('currency', 3);
            $t->json('adjustments')->nullable();         // applied discounts/taxes
            $t->timestampsTz();
            $t->unique(['cart_id', 'variant_id']);
        });

        Schema::create($p.'orders', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('number')->unique();              // ORD-2026-000123
            $t->foreignId('customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->string('channel_code', 32);
            $t->char('currency', 3);
            $t->char('fx_currency', 3)->nullable();       // currency used for accounting
            $t->decimal('fx_rate', 18, 8)->nullable();
            $t->string('state', 32)->default('cart');     // cart|draft|placed|paid|partially_fulfilled|fulfilled|closed|cancelled|refunded
            $t->bigInteger('subtotal_minor')->default(0);
            $t->bigInteger('tax_total_minor')->default(0);
            $t->bigInteger('shipping_minor')->default(0);
            $t->bigInteger('discount_total_minor')->default(0);
            $t->bigInteger('grand_total_minor')->default(0);
            $t->unsignedInteger('item_count')->default(0);
            $t->string('locale', 8);
            $t->string('email')->nullable();
            $t->json('billing_address_snapshot')->nullable();
            $t->json('shipping_address_snapshot')->nullable();
            $t->json('metadata')->nullable();
            $t->timestampTz('placed_at')->nullable();
            $t->timestampTz('paid_at')->nullable();
            $t->timestampTz('fulfilled_at')->nullable();
            $t->timestampTz('closed_at')->nullable();
            $t->timestampTz('cancelled_at')->nullable();
            $t->timestampsTz();
            $t->softDeletes();
            $t->index(['state', 'channel_code']);
        });

        Schema::create($p.'order_items', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('order_id')->constrained($p.'orders')->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->restrictOnDelete();
            $t->string('name');
            $t->string('sku', 64);
            $t->unsignedInteger('quantity');
            $t->bigInteger('unit_price_minor');
            $t->bigInteger('unit_tax_minor')->default(0);
            $t->char('currency', 3);
            $t->decimal('tax_rate_percent', 8, 4)->default(0);
            $t->boolean('tax_inclusive')->default(false);
            $t->json('metadata')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'order_adjustments', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('order_id')->constrained($p.'orders')->cascadeOnDelete();
            $t->foreignId('order_item_id')->nullable()->constrained($p.'order_items')->cascadeOnDelete();
            $t->foreignId('discount_id')->nullable()->constrained($p.'discounts')->nullOnDelete();
            $t->string('type', 32);          // discount | surcharge | shipping | tax | fee
            $t->string('name', 128);
            $t->bigInteger('amount_minor');  // negative for discounts
            $t->char('currency', 3);
            $t->timestampsTz();
        });

        Schema::create($p.'order_state_transitions', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('order_id')->constrained($p.'orders')->cascadeOnDelete();
            $t->string('from', 32);
            $t->string('to', 32);
            $t->string('reason')->nullable();
            $t->morphs('actor');             // user, customer, system
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'order_state_transitions');
        Schema::dropIfExists($p.'order_adjustments');
        Schema::dropIfExists($p.'order_items');
        Schema::dropIfExists($p.'orders');
        Schema::dropIfExists($p.'cart_items');
        Schema::dropIfExists($p.'carts');
    }
};
