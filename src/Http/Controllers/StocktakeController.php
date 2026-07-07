<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Stocktake\ApproveStocktake;
use Headless\Accounting\Actions\Stocktake\CancelStocktake;
use Headless\Accounting\Actions\Stocktake\CreateStocktake;
use Headless\Accounting\Actions\Stocktake\PostStocktake;
use Headless\Accounting\Actions\Stocktake\RecordCount;
use Headless\Accounting\Actions\Stocktake\SubmitStocktakeForApproval;
use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\StocktakeLine;
use Headless\Accounting\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StocktakeController
{
    public function index(Request $request): JsonResponse
    {
        $query = Stocktake::query()->with('warehouse');
        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }
        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocktakes = $query->paginate();
        $data = $stocktakes->getCollection()->map(fn (Stocktake $s) => $this->payload($s))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $stocktakes->total(),
                'per_page' => $stocktakes->perPage(),
                'current_page' => $stocktakes->currentPage(),
            ],
        ]);
    }

    public function show(Stocktake $stocktake): JsonResponse
    {
        $stocktake->load('warehouse', 'lines');

        return response()->json(['data' => $this->payload($stocktake->load('lines'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer',
            'scope' => 'nullable|string|in:full,cycle,zone,variant',
            'variant_ids' => 'nullable|array',
            'variant_ids.*' => 'integer',
            'zone_ids' => 'nullable|array',
            'zone_ids.*' => 'integer',
            'scheduled_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

        $stocktake = (new CreateStocktake)->execute(
            warehouse: $warehouse,
            scope: $data['scope'] ?? Stocktake::SCOPE_FULL,
            variantIds: $data['variant_ids'] ?? null,
            zoneIds: $data['zone_ids'] ?? null,
            scheduledAt: $data['scheduled_at'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return response()->json(['data' => $this->payload($stocktake->load('lines'))], 201);
    }

    public function recordCount(Request $request, Stocktake $stocktake): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'counted_quantity' => 'required|integer|min:0',
            'bin_id' => 'nullable|integer',
            'counter_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:128',
            'recount' => 'nullable|boolean',
        ]);

        $line = (new RecordCount)->execute(
            stocktake: $stocktake,
            variantId: (int) $data['variant_id'],
            countedQuantity: (int) $data['counted_quantity'],
            binId: isset($data['bin_id']) ? (int) $data['bin_id'] : null,
            counterId: isset($data['counter_id']) ? (int) $data['counter_id'] : null,
            reason: $data['reason'] ?? null,
            recount: (bool) ($data['recount'] ?? false),
        );

        return response()->json(['data' => $this->linePayload($line)]);
    }

    public function submitForReview(Stocktake $stocktake): JsonResponse
    {
        $submitted = (new SubmitStocktakeForApproval)->execute($stocktake);

        return response()->json(['data' => $this->payload($submitted)]);
    }

    public function approve(Request $request, Stocktake $stocktake): JsonResponse
    {
        $data = $request->validate([
            'approved_by' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        $approved = (new ApproveStocktake)->execute(
            $stocktake,
            isset($data['approved_by']) ? (int) $data['approved_by'] : null,
            $data['notes'] ?? null,
        );

        return response()->json(['data' => $this->payload($approved)]);
    }

    public function cancel(Request $request, Stocktake $stocktake): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $cancelled = (new CancelStocktake)->execute($stocktake, $data['reason'] ?? null);

        return response()->json(['data' => $this->payload($cancelled)]);
    }

    public function post(Request $request, Stocktake $stocktake): JsonResponse
    {
        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
        ]);

        $posted = (new PostStocktake(app(Journal::class)))->execute(
            $stocktake,
            $data['currency'] ?? null,
        );

        return response()->json(['data' => $this->payload($posted->load('lines', 'postedJournalEntry'))]);
    }

    public function varianceSummary(Stocktake $stocktake): JsonResponse
    {
        return response()->json($stocktake->varianceSummary());
    }

    private function payload(Stocktake $stocktake): array
    {
        return [
            'id' => $stocktake->id,
            'number' => $stocktake->number,
            'warehouse_id' => $stocktake->warehouse_id,
            'state' => $stocktake->state,
            'scope' => $stocktake->scope,
            'scheduled_at' => $stocktake->scheduled_at?->toDateString(),
            'counted_at' => $stocktake->counted_at?->toDateString(),
            'approved_at' => $stocktake->approved_at?->toDateString(),
            'posted_at' => $stocktake->posted_at?->toDateString(),
            'posted_journal_entry_id' => $stocktake->posted_journal_entry_id,
            'approved_by' => $stocktake->approved_by,
            'counters' => $stocktake->counters,
            'notes' => $stocktake->notes,
            'lines' => $stocktake->relationLoaded('lines') ? $stocktake->lines->map(fn (StocktakeLine $l) => $this->linePayload($l))->all() : null,
            'variance_summary' => $stocktake->varianceSummary(),
            'created_at' => $stocktake->created_at?->toIso8601String(),
            'updated_at' => $stocktake->updated_at?->toIso8601String(),
        ];
    }

    private function linePayload(StocktakeLine $line): array
    {
        return [
            'id' => $line->id,
            'stocktake_id' => $line->stocktake_id,
            'variant_id' => $line->variant_id,
            'bin_id' => $line->bin_id,
            'system_quantity' => (int) $line->system_quantity,
            'counted_quantity' => $line->counted_quantity !== null ? (int) $line->counted_quantity : null,
            'variance' => (int) $line->variance,
            'unit_cost_minor' => $line->unit_cost_minor !== null ? (int) $line->unit_cost_minor : null,
            'variance_value_minor' => (int) $line->variance_value_minor,
            'currency' => $line->currency,
            'state' => $line->state,
            'count_round' => (int) $line->count_round,
            'reason' => $line->reason,
            'counted_at' => $line->counted_at?->toIso8601String(),
        ];
    }
}
