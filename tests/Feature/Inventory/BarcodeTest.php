<?php

declare(strict_types=1);

use Headless\Accounting\Models\ProductBarcode;
use Headless\Accounting\Models\ProductVariant;

describe('Product barcodes', function () {

    it('persists multiple barcodes per variant', function () {
        $variant = ProductVariant::factory()->create();

        ProductBarcode::create(['variant_id' => $variant->id, 'barcode' => '1111111111111', 'symbology' => 'EAN13']);
        ProductBarcode::create(['variant_id' => $variant->id, 'barcode' => '2222222222222', 'symbology' => 'EAN13']);
        ProductBarcode::create(['variant_id' => $variant->id, 'barcode' => 'CUSTOM-001', 'symbology' => 'CODE128']);

        expect(ProductBarcode::query()->where('variant_id', $variant->id)->count())->toBe(3);
    });

    it('supports GS1 GTIN lookup via product_variants.gs1_gtin', function () {
        $variant = ProductVariant::factory()->create(['gs1_gtin' => '012345678905']);

        $found = ProductVariant::query()->where('gs1_gtin', '012345678905')->first();

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($variant->id);
    });
});
