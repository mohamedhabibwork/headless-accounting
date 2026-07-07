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
        Schema::create($p.'addresses', function (Blueprint $t) {
            $t->id();
            $t->morphs('owner');
            $t->string('type', 16)->default('shipping'); // shipping | billing | both
            $t->string('company')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('line1');
            $t->string('line2')->nullable();
            $t->string('city');
            $t->string('region', 64)->nullable();
            $t->string('postal_code', 32)->nullable();
            $t->char('country_code', 2);
            $t->string('phone', 32)->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'addresses');
    }
};
