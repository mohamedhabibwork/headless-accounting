<?php

declare(strict_types=1);

use Headless\Accounting\Models\Product;
use Headless\Accounting\Models\ProductVariant;

describe('Item types', function () {

    it('persists item_type on products', function () {
        $product = Product::factory()->create(['item_type' => 'raw_material']);
        expect($product->fresh()->item_type)->toBe('raw_material');
    });

    it('flags batch_tracked on variants and exposes helper', function () {
        $variant = ProductVariant::factory()->create(['batch_tracked' => true]);
        expect($variant->fresh()->isBatchTracked())->toBeTrue();
    });

    it('flags serial_tracked on variants and exposes helper', function () {
        $variant = ProductVariant::factory()->create(['serial_tracked' => true]);
        expect($variant->fresh()->isSerialTracked())->toBeTrue();
    });

    it('computes suggested reorder quantity from max - min when no explicit reorder_quantity', function () {
        $variant = ProductVariant::factory()->create([
            'min_stock' => 10,
            'max_stock' => 100,
            'reorder_quantity' => 0,
        ]);

        expect($variant->fresh()->suggestedReorderQuantity())->toBe(90);
    });

    it('persists reorder point', function () {
        $variant = ProductVariant::factory()->create(['reorder_point' => 25]);

        expect((int) $variant->fresh()->reorder_point)->toBe(25);
    });
});
