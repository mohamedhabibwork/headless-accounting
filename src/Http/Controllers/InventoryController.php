<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Inventory\QuarantineExpiredStock;
use Headless\Accounting\Actions\Inventory\ReleaseExpiredReservation;
use Headless\Accounting\Inventory\BatchService;
use Headless\Accounting\Inventory\InventoryValuationService;
use Headless\Accounting\Models\StockItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * InventoryController — meta/aggregated endpoints that don't fit
 * cleanly on a single document:
 *
 *  - valuation: per-company inventory value via {@see InventoryValuationService}
 *  - availability: per-variant / per-location on-hand stock
 *  - expiring: batches due to expire within N days
 *  - sweep: nightly house-keeping (release reservations, quarantine expired batches)
 */
class InventoryController
{
    public function valuation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|integer',
            'as_of' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
        ]);

        $rows = app(InventoryValuationService::class)->valuationAsOf(
            (int) $data['company_id'],
            $data['as_of'] ?? null,
            $data['currency'] ?? null,
        );

        return response()->json([
            'company_id' => (int) $data['company_id'],
            'as_of' => $data['as_of'] ?? now()->toDateString(),
            'currency' => $data['currency'] ?? null,
            'data' => $rows,
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|integer',
            'variant_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
        ]);

        $query = StockItem::query()->with('variant', 'location');

        if (! empty($data['variant_id'])) {
            $query->where('variant_id', (int) $data['variant_id']);
        }

        if (! empty($data['location_id'])) {
            $query->where('location_id', (int) $data['location_id']);
        }

        $rows = $query->orderBy('variant_id')->orderBy('location_id')->paginate();
        $items = collect($rows->items())->map(fn (StockItem $s) => [
            'id' => $s->id,
            'variant_id' => $s->variant_id,
            'location_id' => $s->location_id,
            'bin_id' => $s->bin_id ?? null,
            'on_hand' => (int) $s->on_hand,
            'reserved' => (int) $s->reserved,
            'incoming' => (int) ($s->incoming ?? 0),
            'available' => (int) $s->on_hand - (int) $s->reserved,
            'variant' => $s->relationLoaded('variant') && $s->variant ? [
                'id' => $s->variant->id,
                'sku' => $s->variant->sku,
                'name' => $s->variant->name,
            ] : null,
            'location' => $s->relationLoaded('location') && $s->location ? [
                'id' => $s->location->id,
                'code' => $s->location->code,
                'name' => $s->location->name,
            ] : null,
        ])->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function expiring(Request $request): JsonResponse
    {
        $data = $request->validate([
            'within_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $withinDays = (int) ($data['within_days'] ?? 30);
        $batches = app(BatchService::class)->findNearExpiry($withinDays);

        return response()->json([
            'within_days' => $withinDays,
            'count' => is_iterable($batches) ? count($batches) : (is_array($batches) ? count($batches) : 0),
            'data' => collect($batches)->map(fn ($b) => [
                'id' => $b->id,
                'variant_id' => $b->variant_id,
                'batch_number' => $b->batch_number,
                'status' => $b->status,
                'expiration_date' => $b->expiration_date?->toDateString(),
                'days_to_expiry' => $b->expiration_date ? (int) now()->diffInDays($b->expiration_date, false) : null,
            ])->all(),
        ]);
    }

    public function sweep(Request $request): JsonResponse
    {
        $data = $request->validate([
            'release_reservations' => 'sometimes|boolean',
            'quarantine_expired' => 'sometimes|boolean',
            'limit' => 'nullable|integer|min:1|max:10000',
        ]);

        $releaseReservations = (bool) ($data['release_reservations'] ?? false);
        $quarantineExpired = (bool) ($data['quarantine_expired'] ?? false);
        $limit = isset($data['limit']) ? (int) $data['limit'] : null;

        $released = $releaseReservations
            ? app(ReleaseExpiredReservation::class)->execute($limit)
            : 0;
        $quarantined = $quarantineExpired
            ? app(QuarantineExpiredStock::class)->execute($limit)
            : 0;

        return response()->json([
            'reservations_released' => (int) $released,
            'batches_quarantined' => (int) $quarantined,
        ]);
    }
}
