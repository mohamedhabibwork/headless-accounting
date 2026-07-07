<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Enums\Inventory\BatchStatus;
use Headless\Accounting\Inventory\BatchService;
use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BatchController — lot/batch master management plus the
 * batch-level ops the warehouse team needs (lookups by
 * expiry window, manual quarantine, etc.).
 */
class BatchController
{
    public function index(Request $request): JsonResponse
    {
        $query = Batch::query()->with('variant');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($variantId = $request->query('variant_id')) {
            $query->where('variant_id', (int) $variantId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', '%'.$search.'%')
                    ->orWhere('supplier_batch_number', 'like', '%'.$search.'%')
                    ->orWhere('production_batch_number', 'like', '%'.$search.'%');
            });
        }

        if ($nearExpiry = $request->query('near_expiry')) {
            $days = (int) $nearExpiry;
            $query->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', now()->addDays($days))
                ->whereDate('expiration_date', '>=', now()->toDateString());
        }

        $batches = $query->orderBy('expiration_date')->paginate();
        $data = $batches->getCollection()->map(fn (Batch $b) => $this->payload($b))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $batches->total(),
                'per_page' => $batches->perPage(),
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
            ],
        ]);
    }

    public function show(Batch $batch): JsonResponse
    {
        $batch->load('variant', 'batchStocks.location', 'serialNumbers');

        return response()->json(['data' => $this->payload($batch)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'batch_number' => 'required|string|max:64',
            'supplier_batch_number' => 'nullable|string|max:64',
            'production_batch_number' => 'nullable|string|max:64',
            'manufacturing_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'attributes' => 'nullable|array',
        ]);

        $variant = ProductVariant::query()->findOrFail($data['variant_id']);

        $batch = app(BatchService::class)->create(
            variant: $variant,
            batchNumber: $data['batch_number'],
            manufacturingDate: $data['manufacturing_date'] ?? null,
            expirationDate: $data['expiration_date'] ?? null,
            supplierBatchNumber: $data['supplier_batch_number'] ?? null,
            productionBatchNumber: $data['production_batch_number'] ?? null,
            attributes: $data['attributes'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return response()->json(['data' => $this->payload($batch->fresh()->load('variant'))], 201);
    }

    public function update(Request $request, Batch $batch): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|string|in:'.implode(',', BatchStatus::values()),
            'expiration_date' => 'sometimes|nullable|date',
            'manufacturing_date' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:500',
            'attributes' => 'sometimes|nullable|array',
        ]);

        $batch->update($data);

        return response()->json(['data' => $this->payload($batch->fresh()->load('variant'))]);
    }

    public function destroy(Batch $batch): JsonResponse
    {
        $batch->delete();

        return response()->json(['deleted' => true]);
    }

    public function nearExpiry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'within_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $withinDays = (int) ($data['within_days'] ?? 30);
        $batches = app(BatchService::class)->findNearExpiry($withinDays);

        $items = collect($batches)->map(function (Batch $b) {
            $payload = $this->payload($b);
            $payload['days_to_expiry'] = $b->expiration_date?->diffInDays(now(), false) !== null
                ? (int) now()->diffInDays($b->expiration_date, false)
                : null;

            return $payload;
        })->all();

        return response()->json([
            'within_days' => $withinDays,
            'data' => $items,
        ]);
    }

    public function quarantine(Batch $batch): JsonResponse
    {
        $batch->status = BatchStatus::Quarantined;
        $batch->save();

        return response()->json(['data' => $this->payload($batch->fresh()->load('variant'))]);
    }

    private function payload(Batch $batch): array
    {
        return [
            'id' => $batch->id,
            'company_id' => $batch->company_id,
            'variant_id' => $batch->variant_id,
            'batch_number' => $batch->batch_number,
            'supplier_batch_number' => $batch->supplier_batch_number,
            'production_batch_number' => $batch->production_batch_number,
            'manufacturing_date' => $batch->manufacturing_date?->toDateString(),
            'expiration_date' => $batch->expiration_date?->toDateString(),
            'status' => $batch->status instanceof BatchStatus ? $batch->status->value : $batch->status,
            'attributes' => $batch->attributes,
            'notes' => $batch->notes,
            'variant' => $batch->relationLoaded('variant') && $batch->variant ? [
                'id' => $batch->variant->id,
                'sku' => $batch->variant->sku,
                'name' => $batch->variant->name,
            ] : null,
            'stock_locations' => $batch->relationLoaded('batchStocks') ? $batch->batchStocks->map(fn ($s) => [
                'id' => $s->id,
                'location_id' => $s->location_id,
                'bin_id' => $s->bin_id,
                'quantity' => (int) $s->quantity,
                'reserved' => (int) $s->reserved,
                'currency' => $s->currency,
                'unit_cost_minor' => $s->unit_cost_minor !== null ? (int) $s->unit_cost_minor : null,
            ])->all() : null,
            'serial_count' => $batch->relationLoaded('serialNumbers') ? $batch->serialNumbers->count() : null,
            'created_at' => $batch->created_at?->toIso8601String(),
            'updated_at' => $batch->updated_at?->toIso8601String(),
        ];
    }
}
