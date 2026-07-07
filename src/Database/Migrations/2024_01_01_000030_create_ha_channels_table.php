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
        Schema::create($p.'channels', function (Blueprint $t) use ($p) {
            $t->string('code', 32)->primary();
            $t->string('name');
            $t->char('currency', 3);
            $t->string('locale', 8);
            $t->string('tax_zone_code', 64)->nullable();
            $t->boolean('tax_inclusive')->default(false);
            $t->boolean('active')->default(true);
            $t->json('config')->nullable();
            $t->timestampsTz();
            $t->foreign('currency')->references('code')->on($p.'currencies');
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'channels');
    }
};
