<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts Receivable (customers) + Accounts Payable (vendors)
 * complete data model.
 */
return new class extends Migration
{
    private function prefix(): string
    {
        return (string) config('headless-accounting.table_prefix', 'ha_');
    }

    public function up(): void
    {
        $p = $this->prefix();
        Schema::create($p.'vendors', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->string('code', 32)->nullable();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 32)->nullable();
            $t->string('contact_name')->nullable();
            $t->string('tax_id', 64)->nullable();
            $t->string('iban', 64)->nullable();
            $t->string('bic', 16)->nullable();
            $t->string('default_currency', 3)->nullable();
            $t->char('default_locale', 8)->nullable();
            $t->decimal('credit_limit_minor', 18, 0)->default(0);
            $t->char('currency', 3)->nullable();
            $t->unsignedSmallInteger('payment_terms_days')->default(30);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->softDeletes();
            $t->unique(['company_id', 'code']);
        });

        Schema::create($p.'vendor_addresses', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('vendor_id')->constrained($p.'vendors')->cascadeOnDelete();
            $t->string('type', 16)->default('billing');     // billing | shipping | remit-to
            $t->text('address_lines')->nullable();
            $t->string('city', 64)->nullable();
            $t->string('region', 64)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->string('postal_code', 32)->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'bills', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained($p.'vendors')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->char('currency', 3);
            $t->char('fx_currency', 3)->nullable();
            $t->decimal('fx_rate', 18, 8)->nullable();
            $t->bigInteger('subtotal_minor')->default(0);
            $t->bigInteger('tax_minor')->default(0);
            $t->bigInteger('total_minor')->default(0);
            $t->bigInteger('paid_minor')->default(0);
            $t->bigInteger('balance_minor')->default(0);
            $t->date('bill_date');
            $t->date('due_date')->nullable();
            $t->string('state', 16)->default('draft');    // draft | received | paid | partial | void | cancelled
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestampsTz();
            $t->softDeletes();
            $t->index(['company_id', 'state']);
        });

        Schema::create($p.'bill_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('bill_id')->constrained($p.'bills')->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained($p.'products')->nullOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity')->default(1);
            $t->bigInteger('unit_cost_minor');
            $t->char('currency', 3);
            $t->decimal('tax_percent', 8, 4)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained($p.'tax_rates')->nullOnDelete();
            $t->foreignId('account_id')->nullable()->constrained($p.'accounts')->nullOnDelete();  // expense account override
            $t->timestampsTz();
        });

        Schema::create($p.'vendor_debit_notes', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained($p.'vendors')->cascadeOnDelete();
            $t->foreignId('bill_id')->nullable()->constrained($p.'bills')->nullOnDelete();
            $t->string('number')->unique();
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->text('reason');
            $t->string('state', 16)->default('draft');
            $t->date('issued_at');
            $t->timestampsTz();
        });

        Schema::create($p.'vendor_credit_notes', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained($p.'vendors')->cascadeOnDelete();
            $t->foreignId('bill_id')->nullable()->constrained($p.'bills')->nullOnDelete();
            $t->string('number')->unique();
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->text('reason');
            $t->string('state', 16)->default('draft');
            $t->date('issued_at');
            $t->timestampsTz();
        });

        Schema::create($p.'payment_schedules', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->morphs('source');                                     // bill, invoice, etc.
            $t->unsignedSmallInteger('installment_no');
            $t->date('due_date');
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->string('method', 32)->nullable();                    // bank_transfer, check, card
            $t->string('state', 16)->default('pending');             // pending | paid | overdue | cancelled
            $t->date('paid_at')->nullable();
            $t->foreignId('payment_id')->nullable()->constrained($p.'payments')->nullOnDelete();
            $t->timestampsTz();
            $t->index(['due_date', 'state']);
        });

        // Augment customers with credit limit & statement options.
        Schema::table($p.'customers', function (Blueprint $t) use ($p) {
            if (! Schema::hasColumn($p.'customers', 'credit_limit_minor')) {
                $t->decimal('credit_limit_minor', 18, 0)->default(0);
            }
            if (! Schema::hasColumn($p.'customers', 'payment_terms_days')) {
                $t->unsignedSmallInteger('payment_terms_days')->default(30);
            }
            if (! Schema::hasColumn($p.'customers', 'currency')) {
                $t->char('currency', 3)->nullable();
            }
            if (! Schema::hasColumn($p.'customers', 'company_id')) {
                $t->foreignId('company_id')->nullable()->after('id')->constrained($p.'companies')->nullOnDelete();
            }
        });

        Schema::create($p.'customer_debit_notes', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained($p.'customers')->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained($p.'invoices')->nullOnDelete();
            $t->string('number')->unique();
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->text('reason');
            $t->string('state', 16)->default('draft');
            $t->date('issued_at');
            $t->timestampsTz();
        });

        // Receipts / Payment allocations
        Schema::create($p.'payment_allocations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('payment_id')->constrained($p.'payments')->cascadeOnDelete();
            $t->morphs('target');                                     // invoice, bill, debit_note, credit_note, etc.
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->decimal('fx_rate', 18, 8)->default(1.0);
            $t->date('allocated_at');
            $t->timestampsTz();
        });

        Schema::create($p.'write_offs', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->morphs('source');                                     // invoice, bill
            $t->char('currency', 3);
            $t->bigInteger('amount_minor');
            $t->text('reason');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'write_offs');
        Schema::dropIfExists($p.'payment_allocations');
        Schema::dropIfExists($p.'customer_debit_notes');
        Schema::table($p.'customers', function (Blueprint $t) use ($p) {
            foreach (['credit_limit_minor', 'payment_terms_days', 'currency', 'company_id'] as $col) {
                if (Schema::hasColumn($p.'customers', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists($p.'payment_schedules');
        Schema::dropIfExists($p.'vendor_credit_notes');
        Schema::dropIfExists($p.'vendor_debit_notes');
        Schema::dropIfExists($p.'bill_lines');
        Schema::dropIfExists($p.'bills');
        Schema::dropIfExists($p.'vendor_addresses');
        Schema::dropIfExists($p.'vendors');
    }
};
