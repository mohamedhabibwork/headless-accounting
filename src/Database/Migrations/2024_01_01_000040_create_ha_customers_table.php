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
        Schema::create($p.'customers', function (Blueprint $t) {
            $t->id();
            $t->morphs('owner');
            $t->string('email')->index();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('company')->nullable();
            $t->string('vat_id', 32)->nullable();
            $t->string('phone', 32)->nullable();
            $t->char('default_currency', 3)->nullable();
            $t->string('default_locale', 8)->nullable();
            $t->timestampTz('email_verified_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestampsTz();
            $t->softDeletes();
        });

        Schema::create($p.'customer_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('code', 64)->unique();
            $t->text('description')->nullable();
            $t->boolean('tax_exempt')->default(false);
            $t->timestampsTz();
        });

        Schema::create($p.'customer_group_members', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('customer_id')->constrained($p.'customers')->cascadeOnDelete();
            $t->foreignId('customer_group_id')->constrained($p.'customer_groups')->cascadeOnDelete();
            $t->timestampsTz();
            $t->unique(['customer_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'customer_group_members');
        Schema::dropIfExists($p.'customer_groups');
        Schema::dropIfExists($p.'customers');
    }
};
