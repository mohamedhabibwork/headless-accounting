<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Inventory\ReleaseExpiredReservation;
use Headless\Accounting\Actions\Inventory\ReserveStock;
use Headless\Accounting\Models\Cart;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\ReservationEvent;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockReservation;

describe('ReleaseExpiredReservation action', function () {

    it('releases reservations past their expiry', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-RES', 'name' => 'Res WH', 'active' => true]);
        $stockItem = StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'on_hand' => 100,
        ]);
        $cart = Cart::create([
            'token' => 'cart-'.uniqid(),
            'channel_code' => 'web',
            'currency' => 'EUR',
            'locale' => 'en',
        ]);

        $reservation = app(ReserveStock::class)->execute(
            variant: $variant,
            location: $location,
            quantity: 5,
            source: $cart,
        );

        $stockItem->refresh();
        expect((int) $stockItem->reserved)->toBe(5);

        StockReservation::query()->where('id', $reservation->id)->update([
            'expires_at' => now()->subDay(),
        ]);

        $released = app(ReleaseExpiredReservation::class)->execute();

        expect($released)->toBe(1);

        $stockItem->refresh();
        expect((int) $stockItem->reserved)->toBe(0);

        $event = ReservationEvent::query()->where('stock_reservation_id', $reservation->id)->where('event', 'expired')->first();
        expect($event)->not->toBeNull();
    });

    it('does not release reservations still in the future', function () {
        $variant = ProductVariant::factory()->create();
        $location = Location::create(['code' => 'WH-RES2', 'name' => 'Res WH 2', 'active' => true]);
        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'on_hand' => 100,
        ]);
        $cart = Cart::create([
            'token' => 'cart-'.uniqid(),
            'channel_code' => 'web',
            'currency' => 'EUR',
            'locale' => 'en',
        ]);

        app(ReserveStock::class)->execute(
            variant: $variant,
            location: $location,
            quantity: 5,
            source: $cart,
        );

        $released = app(ReleaseExpiredReservation::class)->execute();

        expect($released)->toBe(0);

        $stockItem = StockItem::query()->where('variant_id', $variant->id)->where('location_id', $location->id)->first();
        expect((int) $stockItem->reserved)->toBe(5);
    });
});
