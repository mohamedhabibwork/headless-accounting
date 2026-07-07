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
        Schema::create($p.'discounts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 64)->nullable()->unique();    // coupon code, if any
            $t->string('type', 64);                          // driver class
            $t->boolean('active')->default(true);
            $t->boolean('stackable')->default((bool) config('headless-accounting.discounts.stackable'));
            $t->unsignedSmallInteger('priority')->default((int) config('headless-accounting.discounts.default_priority'));
            $t->json('config')->nullable();                  // type-specific
            $t->timestampTz('starts_at')->nullable();
            $t->timestampTz('ends_at')->nullable();
            $t->foreignId('channel_code')->nullable();       // channel scope
            $t->morphs('owner');                             // who created/owns the discount
            $t->timestampsTz();
            $t->index(['active', 'starts_at', 'ends_at']);
        });

        Schema::create($p.'discount_targets', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('discount_id')->constrained($p.'discounts')->cascadeOnDelete();
            $t->morphs('target');                            // order, item, channel, customer
            $t->timestampsTz();
        });

        Schema::create($p.'discount_conditions', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('discount_id')->constrained($p.'discounts')->cascadeOnDelete();
            $t->string('type', 64);
            $t->json('config')->nullable();
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'discount_limitations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('discount_id')->constrained($p.'discounts')->cascadeOnDelete();
            $t->string('type', 64);
            $t->json('config')->nullable();
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'discount_usages', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('discount_id')->constrained($p.'discounts')->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->morphs('source');                            // order that triggered use
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->timestampTz('used_at')->useCurrent();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'discount_usages');
        Schema::dropIfExists($p.'discount_limitations');
        Schema::dropIfExists($p.'discount_conditions');
        Schema::dropIfExists($p.'discount_targets');
        Schema::dropIfExists($p.'discounts');
    }
};
