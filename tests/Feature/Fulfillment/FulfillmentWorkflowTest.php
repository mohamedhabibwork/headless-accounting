<?php

declare(strict_types=1);

use Headless\Accounting\Actions\Fulfillment\CreatePickList;
use Headless\Accounting\Actions\Fulfillment\MarkDelivered;
use Headless\Accounting\Actions\Fulfillment\PackShipment;
use Headless\Accounting\Actions\Fulfillment\PickStock;
use Headless\Accounting\Actions\Fulfillment\ShipOrder;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Fulfillment\FulfillmentPlanBuilder;
use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\PackStation;
use Headless\Accounting\Models\PickList;
use Headless\Accounting\Models\PickListLine;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Shipment;
use Headless\Accounting\Models\ShippingRateCard;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseZone;

beforeEach(function () {
    $this->order = (new CreateOrder)->execute(currency: 'EUR');
    $this->order->update(['shipping_address_snapshot' => ['country' => 'FR']]);

    $this->warehouse = Warehouse::factory()->create(['code' => 'WH-FUL']);
    $this->warehouse->update([
        'location_id' => Location::create(['code' => 'WH-FUL-LOC', 'name' => 'Fulfilment'])->id,
    ]);
    WarehouseZone::factory()->pickFace()->create(['warehouse_id' => $this->warehouse->id]);
    WarehouseZone::factory()->packing()->create(['warehouse_id' => $this->warehouse->id]);

    $variant = ProductVariant::factory()->create();
    StockItem::create([
        'variant_id' => $variant->id,
        'location_id' => $this->warehouse->location_id,
        'on_hand' => 50,
    ]);
    $this->variant = $variant;

    $carrier = Carrier::factory()->create(['code' => 'dhl']);
    ShippingRateCard::factory()->create([
        'carrier_id' => $carrier->id,
        'warehouse_id' => $this->warehouse->id,
        'service_code' => 'express',
        'base_cost_minor' => 1200,
        'per_kg_cost_minor' => 0,
        'currency' => 'EUR',
        'destinations' => ['FR'],
    ]);

    $plan = app(FulfillmentPlanBuilder::class)->build(
        $this->order,
        [['variant_id' => $variant->id, 'quantity' => 3, 'weight_grams' => 500]],
        FulfillmentPlan::STRATEGY_PRIORITY,
    );
    $this->plan = $plan;
});

describe('CreatePickList action', function () {
    it('creates one pick list per warehouse', function () {
        $pickList = (new CreatePickList)->execute($this->plan);

        expect($pickList)->toBeInstanceOf(PickList::class);
        expect((int) PickList::query()->where('fulfillment_plan_id', $this->plan->id)->count())->toBe(1);
        expect((int) PickListLine::query()->where('pick_list_id', $pickList->id)->count())->toBe(1);
        expect((int) PickListLine::query()->where('pick_list_id', $pickList->id)->sum('quantity_requested'))->toBe(3);
        expect($this->plan->fresh()->state)->toBe(FulfillmentPlan::STATE_PICKING);
    });
});

describe('PickStock action', function () {
    it('decrements on_hand and writes a pick StockMovement', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();

        $picked = (new PickStock)->execute(
            pickList: $pickList,
            variantId: $line->variant_id,
            pickedQuantity: 3,
            pickerName: 'Alice',
        );

        expect((int) $picked->quantity_picked)->toBe(3);
        expect($picked->state)->toBe(PickListLine::STATE_PICKED);
        $stockItem = StockItem::query()->where('variant_id', $line->variant_id)->first();
        expect((int) $stockItem->on_hand)->toBe(47);
        $movement = StockMovement::query()
            ->where('stock_item_id', $stockItem->id)
            ->where('reason', 'pick')
            ->latest('id')
            ->first();
        expect((int) $movement->quantity)->toBe(-3);
        expect($pickList->fresh()->state)->toBe(PickList::STATE_PICKED);
    });

    it('marks the line short when picked below requested', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();

        $picked = (new PickStock)->execute(
            pickList: $pickList,
            variantId: $line->variant_id,
            pickedQuantity: 1,
        );

        expect($picked->state)->toBe(PickListLine::STATE_SHORT);
        expect($picked->shortage())->toBe(2);
        expect($pickList->fresh()->state)->toBe(PickList::STATE_PICKING);
    });

    it('refuses to pick more units than on hand', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();

        expect(fn () => (new PickStock)->execute(
            pickList: $pickList,
            variantId: $line->variant_id,
            pickedQuantity: 999,
        ))->toThrow(AccountingException::class);
    });
});

describe('PackShipment action', function () {
    it('creates a pack station once the pick list is fully picked', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();

        (new PickStock)->execute($pickList, $line->variant_id, 3);

        $pack = (new PackShipment)->execute(
            pickList: $pickList,
            cartonType: 'box-m',
            weightGrams: 1500.0,
            lengthMm: 300.0,
            widthMm: 200.0,
            heightMm: 100.0,
            packerName: 'Bob',
        );

        expect($pack)->toBeInstanceOf(PackStation::class);
        expect($pack->state)->toBe(PackStation::STATE_PACKED);
        expect((int) $pack->totalItems())->toBe(3);
        expect($pickList->fresh()->state)->toBe(PickList::STATE_PACKED);
    });

    it('refuses to pack a list with shortages unless allowed', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();

        (new PickStock)->execute($pickList, $line->variant_id, 1);

        expect(fn () => (new PackShipment)->execute(
            pickList: $pickList,
            cartonType: 'box-m',
            weightGrams: 1000.0,
            lengthMm: 200.0,
            widthMm: 200.0,
            heightMm: 100.0,
        ))->toThrow(AccountingException::class);

        // Allowed override
        $pack = (new PackShipment)->execute(
            pickList: $pickList,
            cartonType: 'box-m',
            weightGrams: 1000.0,
            lengthMm: 200.0,
            widthMm: 200.0,
            heightMm: 100.0,
            allowShortages: true,
        );
        expect($pack)->toBeInstanceOf(PackStation::class);
    });
});

describe('ShipOrder action', function () {
    it('turns a pack station into a Shipment and advances the order state', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();
        (new PickStock)->execute($pickList, $line->variant_id, 3);
        $pack = (new PackShipment)->execute(
            pickList: $pickList,
            cartonType: 'box-m',
            weightGrams: 1500.0,
            lengthMm: 300.0,
            widthMm: 200.0,
            heightMm: 100.0,
        );

        $shipment = (new ShipOrder)->execute(
            packStation: $pack,
            carrierCode: 'dhl',
            serviceCode: 'express',
            trackingNumber: 'TRK-001',
        );

        expect($shipment)->toBeInstanceOf(Shipment::class);
        expect($shipment->state)->toBe(Shipment::STATE_SHIPPED);
        expect($shipment->tracking_number)->toBe('TRK-001');
        expect((int) $shipment->cost_minor)->toBe(1200);
        expect((string) $shipment->currency)->toBe('EUR');
        expect($pack->fresh()->state)->toBe(PackStation::STATE_SHIPPED);

        // Order should advance to fulfilled since there's nothing pending.
        expect($this->order->fresh()->state)->toBe(Order::STATE_FULFILLED);
        expect($this->order->fresh()->fulfilled_at)->not->toBeNull();

        // Stock movement recorded for the ship.
        $movement = StockMovement::query()->where('reason', 'ship')->latest('id')->first();
        expect((int) $movement->quantity)->toBe(-3);
    });

    it('rejects an unknown carrier code', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();
        (new PickStock)->execute($pickList, $line->variant_id, 3);
        $pack = (new PackShipment)->execute(
            pickList: $pickList,
            cartonType: 'box-m',
            weightGrams: 1500.0,
            lengthMm: 300.0,
            widthMm: 200.0,
            heightMm: 100.0,
        );

        expect(fn () => (new ShipOrder)->execute(
            packStation: $pack,
            carrierCode: 'unknown_carrier',
            serviceCode: 'express',
        ))->toThrow(AccountingException::class);
    });
});

describe('MarkDelivered action', function () {
    it('flips the shipment to delivered', function () {
        $pickList = (new CreatePickList)->execute($this->plan);
        $line = $pickList->lines()->first();
        (new PickStock)->execute($pickList, $line->variant_id, 3);
        $pack = (new PackShipment)->execute(
            pickList: $pickList,
            cartonType: 'box-m',
            weightGrams: 1500.0,
            lengthMm: 300.0,
            widthMm: 200.0,
            heightMm: 100.0,
        );
        $shipment = (new ShipOrder)->execute(
            packStation: $pack,
            carrierCode: 'dhl',
            serviceCode: 'express',
            trackingNumber: 'TRK-002',
        );

        $delivered = (new MarkDelivered)->execute($shipment);

        expect($delivered->state)->toBe(Shipment::STATE_DELIVERED);
        expect($delivered->delivered_at)->not->toBeNull();
    });
});
