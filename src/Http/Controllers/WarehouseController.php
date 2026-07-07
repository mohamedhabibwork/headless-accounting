<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Fulfillment\CarrierRateShopper;
use Headless\Accounting\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query()->with('zones', 'rateCards');

        if ($active = $request->query('active')) {
            $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($onlyFulfillment = $request->query('fulfillment_enabled')) {
            $query->where('fulfillment_enabled', filter_var($onlyFulfillment, FILTER_VALIDATE_BOOLEAN));
        }

        $warehouses = $query->paginate();
        $data = $warehouses->getCollection()->map(fn (Warehouse $w) => $this->payload($w))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $warehouses->total(),
                'per_page' => $warehouses->perPage(),
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
            ],
        ]);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load('zones.bins', 'rateCards');

        return response()->json(['data' => $this->payload($warehouse)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:32|unique:'.config('headless-accounting.table_prefix', 'ha_').'warehouses,code',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|in:warehouse,store,dropship,transit,pop_up',
            'fulfillment_enabled' => 'nullable|boolean',
            'stocktake_enabled' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'shipping_address' => 'nullable|array',
            'contact' => 'nullable|array',
            'capabilities' => 'nullable|array',
            'opening_hours' => 'nullable|array',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'timezone' => 'nullable|string|max:64',
        ]);

        $warehouse = Warehouse::create($data);

        return response()->json(['data' => $this->payload($warehouse->fresh())], 201);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'fulfillment_enabled' => 'sometimes|boolean',
            'stocktake_enabled' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:0',
            'shipping_address' => 'sometimes|array',
            'contact' => 'sometimes|array',
            'capabilities' => 'sometimes|array',
            'opening_hours' => 'sometimes|array',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'active' => 'sometimes|boolean',
        ]);

        $warehouse->update($data);

        return response()->json(['data' => $this->payload($warehouse->fresh())]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return response()->json(['deleted' => true]);
    }

    public function rateShop(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'country' => 'required|string|size:2',
            'weight_grams' => 'required|numeric|min:0',
            'items_value_minor' => 'nullable|integer|min:0',
            'mode' => 'nullable|string|in:cost,fastest,eta',
        ]);
        $shopper = app(CarrierRateShopper::class);
        $quotes = $shopper->shop(
            warehouse: $warehouse,
            destinationCountry: strtoupper($data['country']),
            weightGrams: (float) $data['weight_grams'],
            itemsValueMinor: (int) ($data['items_value_minor'] ?? 0),
            mode: $data['mode'] ?? CarrierRateShopper::RANK_BY_COST,
        );

        return response()->json([
            'warehouse_id' => $warehouse->id,
            'country' => strtoupper($data['country']),
            'weight_grams' => (float) $data['weight_grams'],
            'quotes' => $quotes,
        ]);
    }

    private function payload(Warehouse $warehouse): array
    {
        return [
            'id' => $warehouse->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'type' => $warehouse->type,
            'fulfillment_enabled' => (bool) $warehouse->fulfillment_enabled,
            'stocktake_enabled' => (bool) $warehouse->stocktake_enabled,
            'is_default' => (bool) $warehouse->is_default,
            'priority' => (int) $warehouse->priority,
            'location_id' => $warehouse->location_id,
            'latitude' => $warehouse->latitude,
            'longitude' => $warehouse->longitude,
            'timezone' => $warehouse->timezone,
            'shipping_address' => $warehouse->shipping_address,
            'contact' => $warehouse->contact,
            'capabilities' => $warehouse->capabilities,
            'opening_hours' => $warehouse->opening_hours,
            'active' => (bool) $warehouse->active,
            'zones' => $warehouse->relationLoaded('zones') ? $warehouse->zones->map(fn ($z) => [
                'id' => $z->id,
                'code' => $z->code,
                'name' => $z->name,
                'kind' => $z->kind,
                'is_default_pick' => (bool) $z->is_default_pick,
                'is_default_pack' => (bool) $z->is_default_pack,
            ])->all() : null,
            'rate_cards' => $warehouse->relationLoaded('rateCards') ? $warehouse->rateCards->map(fn ($c) => [
                'id' => $c->id,
                'carrier_id' => $c->carrier_id,
                'service_code' => $c->service_code,
                'service_name' => $c->service_name,
                'currency' => $c->currency,
                'base_cost_minor' => (int) $c->base_cost_minor,
                'per_kg_cost_minor' => (int) $c->per_kg_cost_minor,
                'eta_days_from' => (int) $c->eta_days_from,
                'eta_days_to' => (int) $c->eta_days_to,
                'priority' => (int) $c->priority,
            ])->all() : null,
            'created_at' => $warehouse->created_at?->toIso8601String(),
            'updated_at' => $warehouse->updated_at?->toIso8601String(),
        ];
    }
}
