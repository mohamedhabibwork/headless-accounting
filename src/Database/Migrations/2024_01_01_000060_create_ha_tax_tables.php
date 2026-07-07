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
        Schema::create($p.'tax_classes', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('slug', 64)->unique();
            $t->text('description')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'tax_zones', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });

        Schema::create($p.'tax_zone_members', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('zone_id')->constrained($p.'tax_zones')->cascadeOnDelete();
            $t->string('country_code', 2)->nullable();
            $t->string('region', 64)->nullable();
            $t->string('postal_code_pattern', 32)->nullable();
            $t->string('operator')->default('or'); // enum: ['or', 'and']
            $t->timestampsTz();
        });

        Schema::create($p.'tax_rates', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('zone_id')->constrained($p.'tax_zones')->cascadeOnDelete();
            $t->foreignId('tax_class_id')->nullable()->constrained($p.'tax_classes')->nullOnDelete();
            $t->string('name');
            $t->decimal('percent', 8, 4);                 // 19.0000 = 19%
            $t->boolean('compound')->default(false);     // tax on tax
            $t->unsignedSmallInteger('priority')->default(1);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'tax_rates');
        Schema::dropIfExists($p.'tax_zone_members');
        Schema::dropIfExists($p.'tax_zones');
        Schema::dropIfExists($p.'tax_classes');
    }
};
