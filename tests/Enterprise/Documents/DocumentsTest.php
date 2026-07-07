<?php

declare(strict_types=1);

use Headless\Accounting\Models\DocumentAttachment;
use Headless\Accounting\Models\PurchaseOrder;
use Headless\Accounting\Tenancy\Company;
use Headless\Accounting\Tests\Traits\CreatesFixtures;

uses(CreatesFixtures::class);

describe('Document management', function () {

    it('records a manually-attached document', function () {
        $co = Company::create(['code' => 'DC', 'name' => 'Doc Co', 'base_currency' => 'EUR']);

        $po = PurchaseOrder::create([
            'company_id' => $co->id, 'number' => 'PO-1',
            'vendor_id' => 1, 'currency' => 'EUR', 'state' => 'open',
        ]);

        // Bypass UploadedFile and call create directly to mimic an upload result.
        $attachment = DocumentAttachment::create([
            'company_id' => $co->id,
            'subject_type' => 'purchase_order',
            'subject_id' => (string) $po->id,
            'filename' => 'spec.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12345,
            'storage_disk' => 'local',
            'storage_path' => 'headless-accounting/purchase_order/'.$po->id.'/spec.pdf',
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        expect($attachment->id)->toBeGreaterThan(0);
        expect($attachment->subject_type)->toBe('purchase_order');
    });
});
