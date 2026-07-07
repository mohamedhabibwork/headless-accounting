<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Models\DeliveryNote;
use Headless\Accounting\Models\GoodsReceipt;
use Headless\Accounting\Models\PurchaseOrder;
use Headless\Accounting\Models\SalesOrder;
use Headless\Accounting\Models\Vendor;
use Headless\Accounting\Procurement\PostGoodsReceipt;
use Headless\Accounting\Sales\PostDeliveryNote;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('Sales & procurement postings', function () {
    it('posts a sales delivery note', function () {
        $co = Company::create(['code' => 'SP', 'name' => 'SP Co', 'base_currency' => 'EUR']);

        $so = SalesOrder::create([
            'company_id' => $co->id, 'number' => 'SO-1',
            'customer_id' => 1, 'currency' => 'EUR', 'state' => 'open',
            'total_minor' => 0,
        ]);

        $dn = DeliveryNote::create([
            'company_id' => $co->id, 'sales_order_id' => $so->id,
            'number' => 'DN-1', 'date' => now()->toDateString(),
            'state' => 'draft', 'total_minor' => 11900,
        ]);

        (new PostDeliveryNote)->execute($dn);
        expect($dn->fresh()->state)->toBeIn(['posted', 'invoiced']);
    });

    it('posts a goods receipt', function () {
        $co = Company::create(['code' => 'SP', 'name' => 'SP Co', 'base_currency' => 'EUR']);
        $vendor = Vendor::create([
            'company_id' => $co->id, 'code' => 'V1', 'name' => 'Vendor 1',
            'currency' => 'EUR',
        ]);

        $po = PurchaseOrder::create([
            'company_id' => $co->id, 'number' => 'PO-1',
            'vendor_id' => $vendor->id, 'currency' => 'EUR', 'state' => 'open',
            'total_minor' => 0,
        ]);

        $gr = GoodsReceipt::create([
            'company_id' => $co->id, 'purchase_order_id' => $po->id,
            'number' => 'GR-1', 'date' => now()->toDateString(),
            'state' => 'draft', 'total_minor' => 5000,
        ]);

        (new PostGoodsReceipt)->execute($gr);
        expect($gr->fresh()->state)->toBeIn(['posted', 'invoiced']);
    });
});
