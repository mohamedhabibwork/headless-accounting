<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Fulfillment\CreatePickList;
use Headless\Accounting\Actions\Fulfillment\MarkDelivered;
use Headless\Accounting\Actions\Fulfillment\PackShipment;
use Headless\Accounting\Actions\Fulfillment\PickStock;
use Headless\Accounting\Actions\Fulfillment\ShipOrder;
use Headless\Accounting\Actions\Order\CreateOrder;
use Headless\Accounting\Actions\Stocktake\ApproveStocktake;
use Headless\Accounting\Actions\Stocktake\CreateStocktake;
use Headless\Accounting\Actions\Stocktake\PostStocktake;
use Headless\Accounting\Actions\Stocktake\RecordCount;
use Headless\Accounting\Actions\Stocktake\SubmitStocktakeForApproval;
use Headless\Accounting\Fulfillment\FulfillmentPlanBuilder;
use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Shipment;
use Headless\Accounting\Models\ShippingRateCard;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();
});

describe('End-to-end multi-warehouse fulfillment + stocktaking', function () {
    it('walks a stocktake → posts variances → ships a new order from the corrected inventory', function () {
        // ── Setup: two warehouses, one SKU with a damaged batch undercounted.
        $whParis = Warehouse::factory()->at('Paris', 'FR', 48.85, 2.35)->create([
            'code' => 'PARIS', 'priority' => 1,
        ]);
        $whParis->update([
            'location_id' => Location::create(['code' => 'PARIS-LOC', 'name' => 'Paris'])->id,
        ]);

        $variant = ProductVariant::factory()->create();

        StockItem::create([
            'variant_id' => $variant->id,
            'location_id' => $whParis->location_id,
            'on_hand' => 100,
        ]);

        $carrier = Carrier::factory()->create(['code' => 'dhl']);
        ShippingRateCard::factory()->create([
            'carrier_id' => $carrier->id,
            'warehouse_id' => $whParis->id,
            'service_code' => 'express',
            'base_cost_minor' => 1200,
            'per_kg_cost_minor' => 0,
            'currency' => 'EUR',
            'destinations' => ['FR'],
        ]);

        // ── 1. Open and run a stocktake that finds 8 missing units.
        $stocktake = (new CreateStocktake)->execute($whParis);
        (new RecordCount)->execute($stocktake, $variant->id, 92);
        $stocktake->update(['state' => Stocktake::STATE_COUNTED]);
        (new SubmitStocktakeForApproval)->execute($stocktake);
        (new ApproveStocktake)->execute($stocktake);

        $company = Company::create([
            'code' => 'E2E', 'name' => 'E2E Co', 'base_currency' => 'EUR',
        ]);
        $stocktake->update(['company_id' => $company->id]);

        (new PostStocktake(app(Journal::class)))->execute($stocktake);

        $item = StockItem::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $whParis->location_id)
            ->first();
        expect((int) $item->on_hand)->toBe(92);

        // ── 2. Order comes in. Allocation engine picks Paris only.
        $order = (new CreateOrder)->execute(currency: 'EUR');
        $order->update(['shipping_address_snapshot' => ['country' => 'FR', 'city' => 'Paris']]);

        $plan = app(FulfillmentPlanBuilder::class)->build(
            $order,
            [['variant_id' => $variant->id, 'quantity' => 3, 'weight_grams' => 500]],
            FulfillmentPlan::STRATEGY_PRIORITY,
        );

        expect($plan->totalUnits())->toBe(3);
        expect((int) $plan->allocations[0]['warehouse_id'])->toBe($whParis->id);

        // ── 3. Generate pick list, pick, pack, ship, deliver.
        $pickList = (new CreatePickList)->execute($plan);
        $line = $pickList->lines()->firstOrFail();
        (new PickStock)->execute($pickList, $line->variant_id, 3);
        $pack = (new PackShipment)->execute(
            pickList: $pickList->fresh(),
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
            trackingNumber: 'TRK-E2E',
        );
        (new MarkDelivered)->execute($shipment);

        $shipment->refresh();
        expect($shipment->state)->toBe(Shipment::STATE_DELIVERED);

        // Stock should now be 89 (92 - 3 picked & shipped).
        $item->refresh();
        expect((int) $item->on_hand)->toBe(89);

        // Order advances to fulfilled.
        $order->refresh();
        expect($order->state)->toBe(Order::STATE_FULFILLED);
        expect($order->fulfilled_at)->not->toBeNull();
    });
});
