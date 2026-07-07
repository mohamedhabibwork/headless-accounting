<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Inventory\AdjustInventory;
use Headless\Accounting\Models\InventoryAdjustment;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * InventoryAdjustmentController — index/show convenience routes
 * plus the post endpoint which executes {@see AdjustInventory}
 * per line. Once all lines run, the document moves to `posted`
 * and the GL link is stored on `journal_entry_id`.
 */
class InventoryAdjustmentController
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryAdjustment::query()->with('location');

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        if ($reason = $request->query('reason')) {
            $query->where('reason', $reason);
        }

        if ($locationId = $request->query('location_id')) {
            $query->where('location_id', (int) $locationId);
        }

        $rows = $query->orderByDesc('id')->paginate();
        $data = $rows->getCollection()->map(fn (InventoryAdjustment $a) => $this->payload($a))->all();

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

    public function show(InventoryAdjustment $adjustment): JsonResponse
    {
        $adjustment->load('location', 'journalEntry');

        return response()->json(['data' => $this->payload($adjustment, true)]);
    }

    public function post(Request $request, InventoryAdjustment $adjustment): JsonResponse
    {
        abort_unless(in_array($adjustment->state, ['draft', 'pending'], true), 422, 'Document must be in draft state to post.');

        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
        ]);

        $currency = $data['currency'] ?? null;
        $location = Location::query()->findOrFail($adjustment->location_id);

        $results = [];
        $lines = $adjustment->lines ?? [];
        foreach ($lines as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantityDelta = (int) ($line['quantity_delta'] ?? $line['quantity'] ?? 0);

            if ($variantId <= 0) {
                continue;
            }

            $variant = ProductVariant::query()->findOrFail($variantId);

            $result = app(AdjustInventory::class)->execute(
                variant: $variant,
                warehouse: $location,
                quantityDelta: $quantityDelta,
                reason: (string) ($line['reason'] ?? $adjustment->reason ?? 'adjustment'),
                binId: $line['bin_id'] ?? null,
                batchNumber: $line['batch_number'] ?? null,
                source: $adjustment,
                currency: $currency,
                unitCostMinor: isset($line['unit_cost_minor']) ? (int) $line['unit_cost_minor'] : null,
            );

            $results[] = [
                'variant_id' => $variantId,
                'quantity_delta' => $quantityDelta,
                'journal_entry_id' => $result['journal_entry_id'] ?? null,
            ];
        }

        $lastJournalId = collect($results)->pluck('journal_entry_id')->filter()->last();

        $adjustment->state = 'posted';
        if ($lastJournalId) {
            $adjustment->journal_entry_id = $lastJournalId;
        }
        $adjustment->save();

        $adjustment->load('location', 'journalEntry');

        return response()->json([
            'data' => $this->payload($adjustment, true),
            'results' => $results,
            'summary' => [
                'lines_posted' => count($results),
            ],
        ]);
    }

    private function payload(InventoryAdjustment $doc, bool $withRelations = false): array
    {
        return [
            'id' => $doc->id,
            'company_id' => $doc->company_id,
            'number' => $doc->number,
            'location_id' => $doc->location_id,
            'adjusted_at' => $doc->adjusted_at?->toDateString(),
            'reason' => $doc->reason,
            'state' => $doc->state,
            'lines' => $doc->lines,
            'notes' => $doc->notes,
            'journal_entry_id' => $doc->journal_entry_id,
            'location' => $withRelations && $doc->relationLoaded('location') ? [
                'id' => $doc->location?->id,
                'code' => $doc->location?->code,
                'name' => $doc->location?->name,
            ] : null,
            'journal_entry' => $withRelations && $doc->relationLoaded('journalEntry') ? [
                'id' => $doc->journalEntry?->id,
                'number' => $doc->journalEntry?->number,
                'state' => $doc->journalEntry?->state,
            ] : null,
            'created_at' => $doc->created_at?->toIso8601String(),
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];
    }
}
