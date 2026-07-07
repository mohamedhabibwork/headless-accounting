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
        // Quotations / Sales Orders / Delivery Notes / Returns
        Schema::create($p.'quotations', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained($p.'customers')->cascadeOnDelete();
            $t->string('number')->unique();
            $t->char('currency', 3);
            $t->string('state', 16)->default('draft');   // draft | sent | accepted | rejected | expired
            $t->date('issue_date');
            $t->date('expiry_date')->nullable();
            $t->json('shipping_address')->nullable();
            $t->json('lines');
            $t->decimal('subtotal_minor', 18, 0)->default(0);
            $t->decimal('discount_minor', 18, 0)->default(0);
            $t->decimal('tax_minor', 18, 0)->default(0);
            $t->decimal('total_minor', 18, 0)->default(0);
            $t->text('notes')->nullable();
            $t->timestampsTz();
            $t->softDeletes();
        });

        Schema::create($p.'sales_orders', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained($p.'customers')->cascadeOnDelete();
            $t->foreignId('quotation_id')->nullable()->constrained($p.'quotations')->nullOnDelete();
            $t->string('number')->unique();
            $t->char('currency', 3);
            $t->string('state', 16)->default('draft');    // draft | confirmed | partially_delivered | delivered | invoiced | closed | cancelled
            $t->date('order_date');
            $t->date('expected_ship_date')->nullable();
            $t->foreignId('warehouse_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->json('shipping_address')->nullable();
            $t->decimal('subtotal_minor', 18, 0)->default(0);
            $t->decimal('discount_minor', 18, 0)->default(0);
            $t->decimal('tax_minor', 18, 0)->default(0);
            $t->decimal('total_minor', 18, 0)->default(0);
            $t->text('notes')->nullable();
            $t->timestampsTz();
            $t->softDeletes();
        });

        Schema::create($p.'sales_order_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('sales_order_id')->constrained($p.'sales_orders')->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained($p.'products')->nullOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained($p.'product_variants')->nullOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price_minor', 18, 0);
            $t->decimal('discount_minor', 18, 0)->default(0);
            $t->decimal('tax_percent', 8, 4)->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'delivery_notes', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('sales_order_id')->nullable()->constrained($p.'sales_orders')->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->string('number')->unique();
            $t->date('ship_date');
            $t->string('state', 16)->default('draft');
            $t->json('lines');
            $t->timestampsTz();
        });

        Schema::create($p.'sales_returns', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained($p.'customers')->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained($p.'invoices')->nullOnDelete();
            $t->string('number')->unique();
            $t->date('return_date');
            $t->char('currency', 3);
            $t->decimal('total_minor', 18, 0)->default(0);
            $t->string('reason')->nullable();
            $t->string('state', 16)->default('draft');
            $t->json('lines');
            $t->timestampsTz();
        });

        // Procurement: Purchase Requests, POs, Goods Receipts, Returns
        Schema::create($p.'purchase_requests', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained($p.'departments')->nullOnDelete();
            $t->string('number')->unique();
            $t->date('needed_by')->nullable();
            $t->string('state', 16)->default('draft');
            $t->json('lines');
            $t->text('justification')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'purchase_orders', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained($p.'vendors')->cascadeOnDelete();
            $t->foreignId('purchase_request_id')->nullable()->constrained($p.'purchase_requests')->nullOnDelete();
            $t->foreignId('requester_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $t->string('number')->unique();
            $t->char('currency', 3);
            $t->string('state', 16)->default('draft');
            $t->date('order_date');
            $t->date('expected_date')->nullable();
            $t->decimal('subtotal_minor', 18, 0)->default(0);
            $t->decimal('tax_minor', 18, 0)->default(0);
            $t->decimal('total_minor', 18, 0)->default(0);
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });

        Schema::create($p.'purchase_order_lines', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained($p.'purchase_orders')->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained($p.'products')->nullOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->decimal('unit_cost_minor', 18, 0);
            $t->decimal('tax_percent', 8, 4)->default(0);
            $t->timestampsTz();
        });

        Schema::create($p.'goods_receipts', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('purchase_order_id')->nullable()->constrained($p.'purchase_orders')->nullOnDelete();
            $t->foreignId('vendor_id')->nullable()->constrained($p.'vendors')->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained($p.'locations')->nullOnDelete();
            $t->string('number')->unique();
            $t->date('received_at');
            $t->string('state', 16)->default('draft');
            $t->json('lines');
            $t->foreignId('journal_entry_id')->nullable()->constrained($p.'journal_entries')->nullOnDelete();
            $t->timestampsTz();
        });

        Schema::create($p.'purchase_returns', function (Blueprint $t) use ($p) {
            $t->id();
            $t->foreignId('company_id')->constrained($p.'companies')->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained($p.'vendors')->cascadeOnDelete();
            $t->foreignId('bill_id')->nullable()->constrained($p.'bills')->nullOnDelete();
            $t->string('number')->unique();
            $t->date('return_date');
            $t->char('currency', 3);
            $t->decimal('total_minor', 18, 0)->default(0);
            $t->string('reason')->nullable();
            $t->string('state', 16)->default('draft');
            $t->json('lines');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        $p = $this->prefix();
        Schema::dropIfExists($p.'purchase_returns');
        Schema::dropIfExists($p.'goods_receipts');
        Schema::dropIfExists($p.'purchase_order_lines');
        Schema::dropIfExists($p.'purchase_orders');
        Schema::dropIfExists($p.'purchase_requests');
        Schema::dropIfExists($p.'sales_returns');
        Schema::dropIfExists($p.'delivery_notes');
        Schema::dropIfExists($p.'sales_order_lines');
        Schema::dropIfExists($p.'sales_orders');
        Schema::dropIfExists($p.'quotations');
    }
};
