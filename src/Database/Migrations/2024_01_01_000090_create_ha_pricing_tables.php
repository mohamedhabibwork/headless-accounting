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
        Schema::create($p.'price_lists', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('name');
            $t->string('code', 64)->unique();
            $t->string('scope', 32)->default('channel');  // channel | customer_group | global
            $t->string('scope_ref', 64)->nullable();
            $t->char('currency', 3);
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->unsignedSmallInteger('priority')->default((int) config('headless-accounting.discounts.default_priority'));
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->foreign('currency')->references('code')->on($p.'currencies');
        });

        Schema::create($p.'prices', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('price_list_id')->constrained($p.'price_lists')->cascadeOnDelete();
            $t->morphs('subject');                              // product or variant
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->decimal('compare_at_minor', 14, 0)->nullable();
            $t->unsignedInteger('min_quantity')->default(1);
            $t->boolean('tax_inclusive')->default(false);
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->timestampsTz();
            $t->index(['subject_type', 'subject_id', 'currency']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'prices');
        Schema::dropIfExists($p.'price_lists');
    }
};
