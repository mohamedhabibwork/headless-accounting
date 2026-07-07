<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Fulfillment\CreatePickList;
use Headless\Accounting\Actions\Fulfillment\PackShipment;
use Headless\Accounting\Actions\Fulfillment\PickStock;
use Headless\Accounting\Actions\Fulfillment\ShipOrder;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Stocktake\ApproveStocktake;
use Headless\Accounting\Actions\Stocktake\CreateStocktake;
use Headless\Accounting\Actions\Stocktake\PostStocktake;
use Headless\Accounting\Actions\Stocktake\RecordCount;
use Headless\Accounting\Events\FulfillmentPlanCreated;
use Headless\Accounting\Events\ShipmentPacked;
use Headless\Accounting\Events\ShipmentShipped;
use Headless\Accounting\Events\StockPicked;
use Headless\Accounting\Events\StocktakeApproved;
use Headless\Accounting\Events\StocktakeCreated;
use Headless\Accounting\Events\StocktakePosted;
use Headless\Accounting\Fulfillment\FulfillmentPlanBuilder;
use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\ShippingRateCard;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Tenancy\Company;
use Illuminate\Support\Facades\Event;

it('fires FulfillmentPlanCreated when a plan is built', function () {
    Event::fake([FulfillmentPlanCreated::class]);

    $warehouse = Warehouse::factory()->create(['code' => 'WH-EV']);
    $warehouse->update(['location_id' => Location::create(['code' => 'WH-EV-LOC', 'name' => 'EV'])->id]);
    $variant = ProductVariant::factory()->create();
    StockItem::create(['variant_id' => $variant->id, 'location_id' => $warehouse->location_id, 'on_hand' => 5]);

    $order = (new CreateOrder)->execute(currency: 'EUR');
    $order->update(['shipping_address_snapshot' => ['country' => 'FR']]);

    $plan = app(FulfillmentPlanBuilder::class)->build(
        $order,
        [['variant_id' => $variant->id, 'quantity' => 2, 'weight_grams' => 100]],
        FulfillmentPlan::STRATEGY_PRIORITY,
    );

    Event::assertDispatched(FulfillmentPlanCreated::class, fn ($event) => $event->plan->is($plan));
});

it('fires StocktakeCreated + Approved + Posted', function () {
    Event::fake([StocktakeCreated::class, StocktakeApproved::class, StocktakePosted::class]);

    $company = Company::create(['code' => 'EV', 'name' => 'Ev Co', 'base_currency' => 'EUR']);
    $warehouse = Warehouse::factory()->create();
    $warehouse->update(['location_id' => Location::create(['code' => 'WH-EV2', 'name' => 'E'])->id]);
    $variant = ProductVariant::factory()->create();
    StockItem::create(['variant_id' => $variant->id, 'location_id' => $warehouse->location_id, 'on_hand' => 10]);

    $stocktake = (new CreateStocktake)->execute($warehouse);
    Event::assertDispatched(StocktakeCreated::class);

    (new RecordCount)->execute($stocktake, $variant->id, 10);
    $stocktake->update(['state' => Stocktake::STATE_COUNTED, 'company_id' => $company->id]);
    (new ApproveStocktake)->execute($stocktake);
    Event::assertDispatched(StocktakeApproved::class);

    (new PostStocktake(app(Journal::class)))->execute($stocktake);
    Event::assertDispatched(StocktakePosted::class);
});

it('fires StockPicked + ShipmentPacked + ShipmentShipped across the workflow', function () {
    Event::fake([StockPicked::class, ShipmentPacked::class, ShipmentShipped::class]);

    $warehouse = Warehouse::factory()->create();
    $warehouse->update(['location_id' => Location::create(['code' => 'WH-FLOW', 'name' => 'F'])->id]);
    $variant = ProductVariant::factory()->create();
    StockItem::create(['variant_id' => $variant->id, 'location_id' => $warehouse->location_id, 'on_hand' => 10]);

    $order = (new CreateOrder)->execute(currency: 'EUR');
    $order->update(['shipping_address_snapshot' => ['country' => 'FR']]);
    $plan = app(FulfillmentPlanBuilder::class)->build(
        $order,
        [['variant_id' => $variant->id, 'quantity' => 2, 'weight_grams' => 200]],
        FulfillmentPlan::STRATEGY_PRIORITY,
    );

    $carrier = Carrier::factory()->create(['code' => 'dhl']);
    ShippingRateCard::factory()->create([
        'carrier_id' => $carrier->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $pickList = (new CreatePickList)->execute($plan);
    $line = $pickList->lines()->first();
    (new PickStock)->execute($pickList, $line->variant_id, 2);
    Event::assertDispatched(StockPicked::class);

    $pack = (new PackShipment)->execute(
        pickList: $pickList->fresh(),
        cartonType: 'box-s',
        weightGrams: 500,
        lengthMm: 200, widthMm: 150, heightMm: 80,
    );
    Event::assertDispatched(ShipmentPacked::class);

    (new ShipOrder)->execute($pack, 'dhl', 'economy', 'TRK-EV');
    Event::assertDispatched(ShipmentShipped::class);
});
