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
        Schema::create($p.'exchange_rates', function (Blueprint $t) use ($p) {
            $t->id();
            $t->char('base_currency', 3);
            $t->char('quote_currency', 3);
            $t->decimal('rate', 18, 8);
            $t->date('effective_at');
            $t->string('source', 64)->default('manual');
            $t->timestampsTz();

            $t->unique(['base_currency', 'quote_currency', 'effective_at'], $p.'exchange_rates_unique');
            $t->foreign('base_currency')->references('code')->on($p.'currencies');
            $t->foreign('quote_currency')->references('code')->on($p.'currencies');
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'exchange_rates');
    }
};
