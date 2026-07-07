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
        Schema::create($p.'invoices', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('number')->unique();           // INV-2026-000001
            $t->foreignId('order_id')->nullable()->constrained($p.'orders')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->string('currency', 3);
            $t->string('state', 16)->default('draft'); // draft|issued|paid|partial|void|cancelled
            $t->bigInteger('subtotal_minor')->default(0);
            $t->bigInteger('tax_total_minor')->default(0);
            $t->bigInteger('grand_total_minor')->default(0);
            $t->bigInteger('paid_minor')->default(0);
            $t->bigInteger('balance_minor')->default(0);
            $t->date('issued_at')->nullable();
            $t->date('due_at')->nullable();
            $t->json('lines')->nullable();
            $t->timestampsTz();
            $t->softDeletes();
        });

        Schema::create($p.'credit_notes', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('invoice_id')->constrained($p.'invoices')->cascadeOnDelete();
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->string('reason')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'payments', function (Blueprint $t) use ($p) {
            $t->id();
            $t->string('number')->unique();          // PAY-2026-000001
            $t->morphs('payable');                   // order, invoice, manual receivable
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->string('driver', 32);                // stripe, paypal, bank, …
            $t->string('method', 32)->nullable();    // card, sepa, ideal, …
            $t->string('state', 16);                 // authorized|captured|partially_refunded|refunded|void|failed|pending
            $t->string('provider_id', 128)->nullable(); // payment intent id, charge id
            $t->string('provider_state', 32)->nullable();
            $t->json('provider_response')->nullable();
            $t->timestampTz('authorized_at')->nullable();
            $t->timestampTz('captured_at')->nullable();
            $t->timestampTz('refunded_at')->nullable();
            $t->timestampTz('voided_at')->nullable();
            $t->foreignId('customer_id')->nullable()->constrained($p.'customers')->nullOnDelete();
            $t->timestampsTz();
            $t->softDeletes();
            $t->index(['driver', 'provider_id']);
        });

        Schema::create($p.'payment_refunds', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('payment_id')->constrained($p.'payments')->cascadeOnDelete();
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->string('provider_refund_id', 128)->nullable();
            $t->string('reason', 256)->nullable();
            $t->json('provider_response')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('driver', 32);
            $t->string('provider_event_id', 191);
            $t->string('event_type', 64);
            $t->json('payload');
            $t->timestampTz('received_at')->useCurrent();
            $t->timestampTz('processed_at')->nullable();
            $t->string('outcome', 32)->nullable();
            $t->index(['driver', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'webhook_events');
        Schema::dropIfExists($p.'payment_refunds');
        Schema::dropIfExists($p.'payments');
        Schema::dropIfExists($p.'credit_notes');
        Schema::dropIfExists($p.'invoices');
    }
};
