<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\AdjustStock;
use Headless\Accounting\Actions\Inventory\ReserveStock;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Cart;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;

describe('AdjustStock action', function () {
    it('creates a stock item when none exists and bumps on_hand by delta', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH1', 'name' => 'Main warehouse', 'active' => true]);

        $movement = (new AdjustStock)->execute(
            variant: $variant, location: $location, delta: +50, reason: 'receive',
        );
        $item = StockItem::query()->where('variant_id', $variant->id)->first();
        expect($item->on_hand)->toBe(50);
        expect($movement->quantity)->toBe(50);
        expect($movement->balance_after)->toBe(50);
    });

    it('decrements existing stock and never goes below zero', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH1', 'name' => 'Main warehouse', 'active' => true]);

        (new AdjustStock)->execute(variant: $variant, location: $location, delta: +10, reason: 'receive');
        (new AdjustStock)->execute(variant: $variant, location: $location, delta: -7, reason: 'pick');
        $item = StockItem::query()->where('variant_id', $variant->id)->first();
        expect($item->on_hand)->toBe(3);
    });
});

describe('ReserveStock action', function () {
    it('creates a reservation and bumps reserved counter', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH1', 'name' => 'Main warehouse', 'active' => true]);
        (new AdjustStock)->execute(variant: $variant, location: $location, delta: +100, reason: 'receive');

        $cart = Cart::create([
            'token' => 'cart-'.uniqid(), 'channel_code' => 'web',
            'currency' => 'EUR', 'locale' => 'en',
        ]);
        $res = (new ReserveStock)->execute(variant: $variant, location: $location, quantity: 5, source: $cart);

        $item = StockItem::query()->where('variant_id', $variant->id)->first();
        expect($item->reserved)->toBe(5);
        expect($item->available())->toBe(95);
        expect($res->quantity)->toBe(5);
        expect($res->expires_at)->not->toBeNull();
    });

    it('refuses to reserve more than the available balance', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH1', 'name' => 'Main warehouse', 'active' => true]);
        (new AdjustStock)->execute(variant: $variant, location: $location, delta: +5, reason: 'receive');

        expect(fn () => (new ReserveStock)->execute(
            variant: $variant, location: $location, quantity: 10,
        ))->toThrow(AccountingException::class);
    });
});
