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
        Schema::create($p.'categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->unsignedSmallInteger('position')->default(0);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'attribute_definitions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->string('type', 32);      // text | select | multiselect | swatch | bool
            $t->boolean('translatable')->default(false);
            $t->json('config')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'products', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('sku')->nullable();
            $t->foreignId('tax_class_id')->nullable()->constrained($p.'tax_classes')->nullOnDelete();
            $t->boolean('stock_tracked')->default(true);
            $t->boolean('active')->default(true);
            $t->json('attributes')->nullable();      // free-form key/value
            $t->timestampsTz();
            $t->softDeletes();
        });

        Schema::create($p.'product_categories', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('product_id')->constrained($p.'products')->cascadeOnDelete();
            $t->foreignId('category_id')->constrained($p.'categories')->cascadeOnDelete();
            $t->unique(['product_id', 'category_id']);
        });

        Schema::create($p.'product_options', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('product_id')->constrained($p.'products')->cascadeOnDelete();
            $t->string('code', 64);
            $t->string('name');
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'product_option_values', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('option_id')->constrained($p.'product_options')->cascadeOnDelete();
            $t->string('value', 128);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'product_variants', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('product_id')->constrained($p.'products')->cascadeOnDelete();
            $t->string('name')->nullable();
            $t->string('sku')->unique();
            $t->string('barcode', 64)->nullable();
            $t->json('option_values')->nullable();      // ['color' => 'red', 'size' => 'M']
            $t->decimal('weight_grams', 10, 2)->nullable();
            $t->decimal('length_mm', 10, 2)->nullable();
            $t->decimal('width_mm', 10, 2)->nullable();
            $t->decimal('height_mm', 10, 2)->nullable();
            $t->boolean('stock_tracked')->default(true);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'variant_attribute_values', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('variant_id')->constrained($p.'product_variants')->cascadeOnDelete();
            $t->foreignId('attribute_id')->constrained($p.'attribute_definitions')->cascadeOnDelete();
            $t->string('locale', 8)->nullable();        // null for non-translatable
            $t->text('value')->nullable();
            $t->index(['variant_id', 'attribute_id', 'locale']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'variant_attribute_values');
        Schema::dropIfExists($p.'product_variants');
        Schema::dropIfExists($p.'product_option_values');
        Schema::dropIfExists($p.'product_options');
        Schema::dropIfExists($p.'product_categories');
        Schema::dropIfExists($p.'products');
        Schema::dropIfExists($p.'attribute_definitions');
        Schema::dropIfExists($p.'categories');
    }
};
