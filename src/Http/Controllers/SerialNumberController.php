<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Inventory\SerialService;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\SerialNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SerialNumberController — unit-level (serial-tracked) inventory ops:
 * register a new serial, move between bins/locations, assign to a
 * customer, return / send for repair / retire, plus the per-serial
 * event history feed.
 */
class SerialNumberController
{
    public function index(Request $request): JsonResponse
    {
        $query = SerialNumber::query()->with('variant', 'batch');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($variantId = $request->query('variant_id')) {
            $query->where('variant_id', (int) $variantId);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('assigned_to_customer_id', (int) $customerId);
        }

        if ($search = $request->query('search')) {
            $query->where('serial', 'like', '%'.$search.'%');
        }

        $rows = $query->orderByDesc('id')->paginate();
        $data = $rows->getCollection()->map(fn (SerialNumber $s) => $this->payload($s))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function show(SerialNumber $serialNumber): JsonResponse
    {
        $serialNumber->load('variant', 'batch', 'location', 'bin', 'customer', 'events');

        return response()->json(['data' => $this->payload($serialNumber, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'serial' => 'required|string|max:128',
            'batch_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
            'bin_id' => 'nullable|integer',
            'manufacturing_date' => 'nullable|date',
            'warranty_expires_at' => 'nullable|date',
            'warranty_terms' => 'nullable|array',
            'attributes' => 'nullable|array',
        ]);

        $variant = ProductVariant::query()->findOrFail($data['variant_id']);

        $serial = app(SerialService::class)->register(
            variant: $variant,
            serial: $data['serial'],
            batchId: $data['batch_id'] ?? null,
            locationId: $data['location_id'] ?? null,
            binId: $data['bin_id'] ?? null,
            manufacturingDate: $data['manufacturing_date'] ?? null,
            warrantyExpiresAt: $data['warranty_expires_at'] ?? null,
            warrantyTerms: $data['warranty_terms'] ?? null,
            attributes: $data['attributes'] ?? null,
        );

        return response()->json(['data' => $this->payload($serial->fresh(), true)], 201);
    }

    public function update(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $data = $request->validate([
            'bin_id' => 'sometimes|nullable|integer',
            'location_id' => 'sometimes|nullable|integer',
        ]);

        $serialNumber->update($data);

        return response()->json(['data' => $this->payload($serialNumber->fresh(), true)]);
    }

    public function destroy(SerialNumber $serialNumber): JsonResponse
    {
        $serialNumber->delete();

        return response()->json(['deleted' => true]);
    }

    public function assign(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'note' => 'nullable|string|max:500',
        ]);

        $serial = app(SerialService::class)->assign(
            serial: $serialNumber,
            customerId: (int) $data['customer_id'],
            note: $data['note'] ?? null,
        );

        return response()->json(['data' => $this->payload($serial->fresh(), true)]);
    }

    public function markReturned(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $data = $request->validate([
            'location_id' => 'nullable|integer',
            'note' => 'nullable|string|max:500',
        ]);

        $serial = app(SerialService::class)->markReturned(
            serial: $serialNumber,
            locationId: $data['location_id'] ?? null,
            note: $data['note'] ?? null,
        );

        return response()->json(['data' => $this->payload($serial->fresh(), true)]);
    }

    public function markUnderRepair(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $serial = app(SerialService::class)->markUnderRepair(
            serial: $serialNumber,
            note: $data['note'] ?? null,
        );

        return response()->json(['data' => $this->payload($serial->fresh(), true)]);
    }

    public function retire(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $serial = app(SerialService::class)->retire(
            serial: $serialNumber,
            note: $data['note'] ?? null,
        );

        return response()->json(['data' => $this->payload($serial->fresh(), true)]);
    }

    public function history(SerialNumber $serialNumber): JsonResponse
    {
        $events = $serialNumber->events()->orderByDesc('occurred_at')->get();

        return response()->json([
            'data' => $events->map(fn ($e) => [
                'id' => $e->id,
                'serial_number_id' => $e->serial_number_id,
                'event' => $e->event,
                'from_status' => $e->from_status,
                'to_status' => $e->to_status,
                'location_id' => $e->location_id,
                'customer_id' => $e->customer_id,
                'note' => $e->note,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    private function payload(SerialNumber $serial, bool $withRelations = false): array
    {
        return [
            'id' => $serial->id,
            'company_id' => $serial->company_id,
            'variant_id' => $serial->variant_id,
            'batch_id' => $serial->batch_id,
            'serial' => $serial->serial,
            'status' => $serial->status,
            'location_id' => $serial->location_id,
            'bin_id' => $serial->bin_id,
            'manufacturing_date' => $serial->manufacturing_date?->toDateString(),
            'warranty_expires_at' => $serial->warranty_expires_at?->toDateString(),
            'sold_at' => $serial->sold_at?->toDateString(),
            'assigned_to_customer_id' => $serial->assigned_to_customer_id,
            'warranty_terms' => $serial->warranty_terms,
            'attributes' => $serial->attributes,
            'variant' => $serial->relationLoaded('variant') && $serial->variant ? [
                'id' => $serial->variant->id,
                'sku' => $serial->variant->sku,
                'name' => $serial->variant->name,
            ] : null,
            'batch' => $serial->relationLoaded('batch') && $serial->batch ? [
                'id' => $serial->batch->id,
                'batch_number' => $serial->batch->batch_number,
            ] : null,
            'location' => $withRelations && $serial->relationLoaded('location') ? [
                'id' => $serial->location?->id,
                'code' => $serial->location?->code,
                'name' => $serial->location?->name,
            ] : null,
            'bin' => $withRelations && $serial->relationLoaded('bin') ? [
                'id' => $serial->bin?->id,
                'code' => $serial->bin?->code,
            ] : null,
            'customer' => $withRelations && $serial->relationLoaded('customer') ? [
                'id' => $serial->customer?->id,
                'name' => $serial->customer?->name,
            ] : null,
            'created_at' => $serial->created_at?->toIso8601String(),
            'updated_at' => $serial->updated_at?->toIso8601String(),
        ];
    }
}
