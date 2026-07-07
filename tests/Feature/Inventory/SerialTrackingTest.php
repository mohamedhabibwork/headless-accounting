<?php

declare(strict_types=1);

use Headless\Accounting\Enums\Inventory\SerialNumberStatus;
use Headless\Accounting\Inventory\SerialService;
use Headless\Accounting\Models\Customer;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\SerialEvent;

describe('Serial number registration and assignment', function () {

    it('registers a serial and assigns it to a customer', function () {
        $variant = ProductVariant::factory()->create(['serial_tracked' => true]);
        $customer = Customer::factory()->create();

        $svc = app(SerialService::class);
        $serial = $svc->register(variantId: $variant->id, serial: 'SN-001');

        $assigned = $svc->assign($serial, $customer->id, note: 'sold at POS');

        expect($serial->fresh()->status)->toBe(SerialNumberStatus::Sold);
        expect($serial->fresh()->assigned_to_customer_id)->toBe($customer->id);
        expect($serial->fresh()->sold_at)->not->toBeNull();

        $event = $serial->fresh()->events()->where('event', 'sold')->first();
        expect($event)->not->toBeNull();
        expect($event)->toBeInstanceOf(SerialEvent::class);
    });

    it('marks a serial returned', function () {
        $variant = ProductVariant::factory()->create(['serial_tracked' => true]);
        $customer = Customer::factory()->create();

        $svc = app(SerialService::class);
        $serial = $svc->register(variantId: $variant->id, serial: 'SN-002');
        $svc->assign($serial, $customer->id);
        $svc->markReturned($serial, note: 'RMA-1');

        $serial->refresh();
        expect($serial->status)->toBe(SerialNumberStatus::Returned);
        expect($serial->events()->where('event', 'returned')->exists())->toBeTrue();
    });

    it('marks a serial under repair then retired', function () {
        $variant = ProductVariant::factory()->create(['serial_tracked' => true]);

        $svc = app(SerialService::class);
        $serial = $svc->register(variantId: $variant->id, serial: 'SN-003');
        $svc->markUnderRepair($serial, note: 'warranty claim');

        expect($serial->fresh()->status)->toBe(SerialNumberStatus::UnderRepair);
        expect($serial->fresh()->events()->where('event', 'repaired')->exists())->toBeTrue();

        $svc->retire($serial, note: 'end of life');
        expect($serial->fresh()->status)->toBe(SerialNumberStatus::Retired);
        expect($serial->fresh()->events()->where('event', 'retired')->exists())->toBeTrue();
    });
});
