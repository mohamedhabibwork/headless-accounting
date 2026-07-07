<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Fulfillment\CreatePickList;
use Headless\Accounting\Actions\Fulfillment\MarkDelivered;
use Headless\Accounting\Actions\Fulfillment\PackShipment;
use Headless\Accounting\Actions\Fulfillment\PickStock;
use Headless\Accounting\Actions\Fulfillment\ShipOrder;
use Headless\Accounting\Fulfillment\FulfillmentPlanBuilder;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\PackStation;
use Headless\Accounting\Models\PickList;
use Headless\Accounting\Models\Shipment;
use Headless\Accounting\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FulfillmentPlanController
{
    public function index(Request $request): JsonResponse
    {
        $query = FulfillmentPlan::query();
        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }
        if ($orderId = $request->query('order_id')) {
            $query->where('order_id', $orderId);
        }

        $plans = $query->paginate();
        $data = $plans->getCollection()->map(fn (FulfillmentPlan $plan) => $this->payload($plan))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $plans->total(),
                'per_page' => $plans->perPage(),
                'current_page' => $plans->currentPage(),
            ],
        ]);
    }

    public function show(FulfillmentPlan $plan): JsonResponse
    {
        $plan->load('pickLists.lines', 'shipments');

        return response()->json(['data' => $this->payload($plan)]);
    }

    public function buildForOrder(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|integer',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.weight_grams' => 'nullable|integer|min:0',
            'strategy' => 'nullable|string|in:cheapest,fastest,closest,priority,manual',
            'preferred_warehouse_id' => 'nullable|integer',
        ]);

        $preferred = $data['preferred_warehouse_id'] ?? null;
        $preferredModel = $preferred ? Warehouse::find($preferred) : null;

        $builder = app(FulfillmentPlanBuilder::class);
        $plan = $builder->build(
            order: $order,
            lines: $data['lines'],
            strategy: $data['strategy'] ?? FulfillmentPlan::STRATEGY_PRIORITY,
            preferred: $preferredModel,
        );

        return response()->json(['data' => $this->payload($plan)], 201);
    }

    public function createPickList(FulfillmentPlan $plan): JsonResponse
    {
        $pickList = (new CreatePickList)->execute($plan);

        return response()->json(['data' => $this->pickListPayload($pickList->load('lines'))], 201);
    }

    public function pickLine(Request $request, PickList $pickList): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'picked_quantity' => 'required|integer|min:0',
            'bin_id' => 'nullable|integer',
            'picker_name' => 'nullable|string|max:128',
            'note' => 'nullable|string|max:255',
        ]);

        $line = (new PickStock)->execute(
            pickList: $pickList,
            variantId: (int) $data['variant_id'],
            pickedQuantity: (int) $data['picked_quantity'],
            binId: isset($data['bin_id']) ? (int) $data['bin_id'] : null,
            pickerName: $data['picker_name'] ?? null,
            note: $data['note'] ?? '',
        );

        return response()->json(['data' => [
            'id' => $line->id,
            'pick_list_id' => $line->pick_list_id,
            'variant_id' => $line->variant_id,
            'bin_id' => $line->bin_id,
            'quantity_requested' => (int) $line->quantity_requested,
            'quantity_picked' => (int) $line->quantity_picked,
            'state' => $line->state,
            'picked_at' => $line->picked_at?->toIso8601String(),
        ]]);
    }

    public function packList(Request $request, PickList $pickList): JsonResponse
    {
        $data = $request->validate([
            'carton_type' => 'required|string|max:32',
            'weight_grams' => 'required|numeric|min:0',
            'length_mm' => 'required|numeric|min:0',
            'width_mm' => 'required|numeric|min:0',
            'height_mm' => 'required|numeric|min:0',
            'packer_name' => 'nullable|string|max:128',
            'allow_shortages' => 'nullable|boolean',
        ]);

        $pack = (new PackShipment)->execute(
            pickList: $pickList,
            cartonType: $data['carton_type'],
            weightGrams: (float) $data['weight_grams'],
            lengthMm: (float) $data['length_mm'],
            widthMm: (float) $data['width_mm'],
            heightMm: (float) $data['height_mm'],
            packerName: $data['packer_name'] ?? null,
            allowShortages: (bool) ($data['allow_shortages'] ?? false),
        );

        return response()->json(['data' => [
            'id' => $pack->id,
            'number' => $pack->number,
            'pick_list_id' => $pack->pick_list_id,
            'carton_type' => $pack->carton_type,
            'weight_grams' => (float) $pack->weight_grams,
            'length_mm' => (float) $pack->length_mm,
            'width_mm' => (float) $pack->width_mm,
            'height_mm' => (float) $pack->height_mm,
            'state' => $pack->state,
            'total_items' => $pack->totalItems(),
        ]], 201);
    }

    public function ship(Request $request, PackStation $packStation): JsonResponse
    {
        $data = $request->validate([
            'carrier_code' => 'required|string|max:32',
            'service_code' => 'required|string|max:32',
            'tracking_number' => 'nullable|string|max:128',
            'tracking_url' => 'nullable|string|max:255',
            'label_url' => 'nullable|string|max:255',
            'cost_minor' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        $shipment = (new ShipOrder)->execute(
            packStation: $packStation,
            carrierCode: $data['carrier_code'],
            serviceCode: $data['service_code'],
            trackingNumber: $data['tracking_number'] ?? null,
            trackingUrl: $data['tracking_url'] ?? null,
            labelUrl: $data['label_url'] ?? null,
            costMinor: isset($data['cost_minor']) ? (string) $data['cost_minor'] : null,
            currency: $data['currency'] ?? null,
        );

        return response()->json(['data' => $this->shipmentPayload($shipment)], 201);
    }

    public function markDelivered(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'delivered_at' => 'nullable|date',
        ]);

        $delivered = (new MarkDelivered)->execute(
            $shipment,
            $data['delivered_at'] ?? null,
        );

        return response()->json(['data' => $this->shipmentPayload($delivered)]);
    }

    private function payload(FulfillmentPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'number' => $plan->number,
            'order_id' => $plan->order_id,
            'state' => $plan->state,
            'strategy' => $plan->strategy,
            'allocations' => $plan->allocations,
            'shipping_options' => $plan->shipping_options,
            'total_units' => $plan->totalUnits(),
            'total_weight_grams' => $plan->totalWeightGrams(),
            'planned_at' => $plan->planned_at?->toIso8601String(),
            'allocated_at' => $plan->allocated_at?->toIso8601String(),
            'completed_at' => $plan->completed_at?->toIso8601String(),
        ];
    }

    private function pickListPayload(PickList $pickList): array
    {
        return [
            'id' => $pickList->id,
            'number' => $pickList->number,
            'fulfillment_plan_id' => $pickList->fulfillment_plan_id,
            'warehouse_id' => $pickList->warehouse_id,
            'state' => $pickList->state,
            'picker_name' => $pickList->picker_name,
            'total_requested' => $pickList->totalQuantityRequested(),
            'total_picked' => $pickList->totalQuantityPicked(),
            'completion_ratio' => $pickList->completionRatio(),
            'lines' => $pickList->lines->map(fn ($l) => [
                'id' => $l->id,
                'variant_id' => $l->variant_id,
                'bin_id' => $l->bin_id,
                'quantity_requested' => (int) $l->quantity_requested,
                'quantity_picked' => (int) $l->quantity_picked,
                'state' => $l->state,
                'pick_sequence' => (int) $l->pick_sequence,
            ])->all(),
        ];
    }

    private function shipmentPayload(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'number' => $shipment->number,
            'order_id' => $shipment->order_id,
            'fulfillment_plan_id' => $shipment->fulfillment_plan_id,
            'warehouse_id' => $shipment->warehouse_id,
            'carrier_code' => $shipment->carrier_code,
            'service_code' => $shipment->service_code,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->trackingUrl(),
            'state' => $shipment->state,
            'weight_grams' => $shipment->weight_grams,
            'cost_minor' => $shipment->cost_minor,
            'currency' => $shipment->currency,
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
        ];
    }
}
