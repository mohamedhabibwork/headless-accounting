<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Models\BatchStock;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BinController — CRUD over {@see WarehouseBin} plus a `contents`
 * endpoint that returns the bin's current stock + variant on-hand
 * summary.
 */
class BinController
{
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseBin::query()->with('zone.warehouse');

        if ($zoneId = $request->query('zone_id')) {
            $query->where('zone_id', (int) $zoneId);
        }

        if ($active = $request->query('active')) {
            $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%')
                    ->orWhere('qr_code', 'like', '%'.$search.'%');
            });
        }

        $bins = $query->orderBy('code')->paginate();
        $data = $bins->getCollection()->map(fn (WarehouseBin $b) => $this->payload($b))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $bins->total(),
                'per_page' => $bins->perPage(),
                'current_page' => $bins->currentPage(),
                'last_page' => $bins->lastPage(),
            ],
        ]);
    }

    public function show(WarehouseBin $bin): JsonResponse
    {
        $bin->load('zone.warehouse', 'batchStocks.batch', 'serialNumbers');

        return response()->json(['data' => $this->payload($bin, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => 'required|integer',
            'code' => 'required|string|max:64',
            'barcode' => 'nullable|string|max:128',
            'qr_code' => 'nullable|string|max:128',
            'aisle' => 'nullable|string|max:32',
            'rack' => 'nullable|string|max:32',
            'shelf' => 'nullable|string|max:32',
            'level' => 'nullable|string|max:32',
            'position' => 'nullable|string|max:32',
            'capacity_units' => 'nullable|numeric|min:0',
            'max_weight_grams' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);

        WarehouseZone::query()->findOrFail($data['zone_id']);

        $bin = WarehouseBin::create($data);

        return response()->json(['data' => $this->payload($bin->fresh(), true)], 201);
    }

    public function update(Request $request, WarehouseBin $bin): JsonResponse
    {
        $data = $request->validate([
            'code' => 'sometimes|string|max:64',
            'barcode' => 'sometimes|nullable|string|max:128',
            'qr_code' => 'sometimes|nullable|string|max:128',
            'aisle' => 'sometimes|nullable|string|max:32',
            'rack' => 'sometimes|nullable|string|max:32',
            'shelf' => 'sometimes|nullable|string|max:32',
            'level' => 'sometimes|nullable|string|max:32',
            'position' => 'sometimes|nullable|string|max:32',
            'capacity_units' => 'sometimes|nullable|numeric|min:0',
            'max_weight_grams' => 'sometimes|nullable|numeric|min:0',
            'active' => 'sometimes|boolean',
        ]);

        $bin->update($data);

        return response()->json(['data' => $this->payload($bin->fresh(), true)]);
    }

    public function destroy(WarehouseBin $bin): JsonResponse
    {
        $bin->delete();

        return response()->json(['deleted' => true]);
    }

    public function contents(WarehouseBin $bin): JsonResponse
    {
        $bin->load('zone.warehouse', 'batchStocks.batch', 'serialNumbers.variant');

        $batchStocks = BatchStock::query()
            ->with('batch.variant')
            ->where('bin_id', $bin->id)
            ->get();

        $variantSummary = $batchStocks
            ->groupBy(fn ($row) => $row->batch?->variant_id)
            ->map(function ($rows, $variantId) {
                $variant = $rows->first()?->batch?->variant;

                return [
                    'variant_id' => (int) $variantId,
                    'sku' => $variant?->sku,
                    'name' => $variant?->name,
                    'quantity_on_hand' => (int) $rows->sum(fn ($r) => (int) $r->quantity),
                    'reserved' => (int) $rows->sum(fn ($r) => (int) $r->reserved),
                    'available' => (int) $rows->sum(fn ($r) => ((int) $r->quantity) - ((int) $r->reserved)),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'bin' => $this->payload($bin->fresh(), true),
                'batch_stocks' => $batchStocks->map(fn ($s) => [
                    'id' => $s->id,
                    'batch_id' => $s->batch_id,
                    'batch_number' => $s->batch?->batch_number,
                    'location_id' => $s->location_id,
                    'bin_id' => $s->bin_id,
                    'quantity' => (int) $s->quantity,
                    'reserved' => (int) $s->reserved,
                    'currency' => $s->currency,
                    'unit_cost_minor' => $s->unit_cost_minor !== null ? (int) $s->unit_cost_minor : null,
                ])->all(),
                'variants' => $variantSummary,
                'serial_count' => $bin->serialNumbers->count(),
            ],
        ]);
    }

    private function payload(WarehouseBin $bin, bool $withRelations = false): array
    {
        return [
            'id' => $bin->id,
            'zone_id' => $bin->zone_id,
            'code' => $bin->code,
            'barcode' => $bin->barcode,
            'qr_code' => $bin->qr_code ?? null,
            'aisle' => $bin->aisle ?? null,
            'rack' => $bin->rack ?? null,
            'shelf' => $bin->shelf ?? null,
            'level' => $bin->level ?? null,
            'position' => $bin->position ?? null,
            'capacity_units' => $bin->capacity_units !== null ? (float) $bin->capacity_units : null,
            'max_weight_grams' => $bin->max_weight_grams !== null ? (float) $bin->max_weight_grams : null,
            'active' => (bool) $bin->active,
            'zone' => $withRelations && $bin->relationLoaded('zone') ? [
                'id' => $bin->zone?->id,
                'code' => $bin->zone?->code,
                'name' => $bin->zone?->name,
                'warehouse_id' => $bin->zone?->warehouse_id,
                'warehouse' => $bin->zone?->relationLoaded('warehouse') ? [
                    'id' => $bin->zone->warehouse?->id,
                    'code' => $bin->zone->warehouse?->code,
                    'name' => $bin->zone->warehouse?->name,
                ] : null,
            ] : null,
            'created_at' => $bin->created_at?->toIso8601String(),
            'updated_at' => $bin->updated_at?->toIso8601String(),
        ];
    }
}
