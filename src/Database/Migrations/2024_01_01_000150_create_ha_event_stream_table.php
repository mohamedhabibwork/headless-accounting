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
        // Generic event stream used by every aggregate root.
        Schema::create($p.'event_stream', function (Blueprint $t) {
            $t->id();
            $t->morphs('subject');
            $t->string('type', 64);
            $t->json('payload')->nullable();
            $t->timestampTz('occurred_at')->useCurrent();
            $t->timestampsTz();
            $t->index(['subject_type', 'subject_id', 'type']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'event_stream');
    }
};
