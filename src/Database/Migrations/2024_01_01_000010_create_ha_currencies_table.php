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
        Schema::create($p.'currencies', function (Blueprint $t) {
            $t->char('code', 3)->primary();
            $t->string('name');
            $t->string('symbol', 8);
            $t->unsignedTinyInteger('decimals')->default(2);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'currencies');
    }
};
