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
        Schema::create($p.'shipments', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('order_id')->constrained($p.'orders')->cascadeOnDelete();
            $t->string('state', 16)->default('pending'); // pending|picked|packed|shipped|delivered|cancelled
            $t->string('carrier', 64)->nullable();
            $t->string('tracking_number', 128)->nullable();
            $t->decimal('weight_grams', 10, 2)->nullable();
            $t->decimal('cost_minor', 18, 0)->nullable();
            $t->char('currency', 3)->nullable();
            $t->json('items');                          // [{variant_id, quantity}, …]
            $t->json('customs')->nullable();
            $t->timestampTz('shipped_at')->nullable();
            $t->timestampTz('delivered_at')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'shipments');
    }
};
