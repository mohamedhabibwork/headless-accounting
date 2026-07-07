<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Inventory\IssueGoods;
use Headless\Accounting\Models\CostCenter;
use Headless\Accounting\Models\GoodsIssue;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\Project;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GoodsIssueController — outbound stock issues
 * (consumption, sampling, damage, sales-issue, production issue, etc).
 *
 * The document is created in `draft` state via `store()`. `update()`
 * and `destroy()` only accept changes while still in `draft`. Posting
 * iterates the JSON `lines` payload, calling {@see IssueGoods} for
 * each variant, and flips the document to `posted` with a journal_entry_id.
 */
class GoodsIssueController
{
    public function index(Request $request): JsonResponse
    {
        $query = GoodsIssue::query()->with('warehouse');

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        if ($reason = $request->query('reason')) {
            $query->where('reason', $reason);
        }

        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        if ($costCenterId = $request->query('cost_center_id')) {
            $query->where('cost_center_id', (int) $costCenterId);
        }

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', (int) $projectId);
        }

        $rows = $query->orderByDesc('id')->paginate();
        $data = $rows->getCollection()->map(fn (GoodsIssue $g) => $this->payload($g))->all();

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

    public function show(GoodsIssue $goodsIssue): JsonResponse
    {
        $goodsIssue->load('warehouse', 'costCenter', 'project', 'journalEntry');

        return response()->json(['data' => $this->payload($goodsIssue, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer',
            'reason' => 'nullable|string|in:sales,consumption,sampling,damage,loss,transfer,production,other',
            'issued_at' => 'required|date',
            'cost_center_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|integer',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.batch_id' => 'nullable|integer',
            'lines.*.bin_id' => 'nullable|integer',
            'lines.*.unit_cost_minor' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        if (! empty($data['cost_center_id'])) {
            CostCenter::query()->findOrFail($data['cost_center_id']);
        }

        if (! empty($data['project_id'])) {
            Project::query()->findOrFail($data['project_id']);
        }

        $warehouse = Location::query()->findOrFail($data['warehouse_id']);

        $document = GoodsIssue::create([
            'company_id' => $request->user()?->company_id,
            'warehouse_id' => $warehouse->id,
            'number' => $this->generateNumber(Config::string('headless-accounting.number_prefixes.goods_issue', 'GI')),
            'reason' => $data['reason'] ?? 'consumption',
            'issued_at' => $data['issued_at'],
            'state' => 'draft',
            'cost_center_id' => $data['cost_center_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'lines' => $data['lines'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $this->payload($document, true)], 201);
    }

    public function update(Request $request, GoodsIssue $goodsIssue): JsonResponse
    {
        abort_unless($goodsIssue->state === 'draft', 422, 'Only draft documents can be updated.');

        $data = $request->validate([
            'reason' => 'sometimes|string|in:sales,consumption,sampling,damage,loss,transfer,production,other',
            'issued_at' => 'sometimes|date',
            'cost_center_id' => 'sometimes|nullable|integer',
            'project_id' => 'sometimes|nullable|integer',
            'lines' => 'sometimes|array|min:1',
            'lines.*.variant_id' => 'required_with:lines|integer',
            'lines.*.quantity' => 'required_with:lines|integer|min:1',
            'lines.*.batch_id' => 'nullable|integer',
            'lines.*.bin_id' => 'nullable|integer',
            'lines.*.unit_cost_minor' => 'nullable|integer',
            'notes' => 'sometimes|nullable|string|max:500',
        ]);

        $goodsIssue->update($data);

        return response()->json(['data' => $this->payload($goodsIssue->fresh(), true)]);
    }

    public function destroy(GoodsIssue $goodsIssue): JsonResponse
    {
        abort_unless($goodsIssue->state === 'draft', 422, 'Only draft documents can be deleted.');

        $goodsIssue->delete();

        return response()->json(['deleted' => true]);
    }

    public function post(Request $request, GoodsIssue $goodsIssue): JsonResponse
    {
        abort_unless($goodsIssue->state === 'draft', 422, 'Only draft documents can be posted.');

        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
        ]);

        $currency = $data['currency'] ?? null;
        $warehouse = Location::query()->findOrFail($goodsIssue->warehouse_id);

        $results = [];
        $lines = $goodsIssue->lines ?? [];
        foreach ($lines as $line) {
            $variant = ProductVariant::query()->findOrFail((int) ($line['variant_id'] ?? 0));
            $quantity = (int) ($line['quantity'] ?? 0);

            $result = app(IssueGoods::class)->execute(
                variant: $variant,
                warehouse: $warehouse,
                quantity: $quantity,
                reason: $goodsIssue->reason,
                binId: $line['bin_id'] ?? null,
                batchNumber: $line['batch_number'] ?? null,
                source: $goodsIssue,
                currency: $currency,
            );

            $results[] = [
                'variant_id' => $variant->id,
                'quantity' => $quantity,
                'journal_entry_id' => $result['journal_entry_id'] ?? null,
            ];
        }

        $lastJournalId = collect($results)->pluck('journal_entry_id')->filter()->last();

        $goodsIssue->state = 'posted';
        if ($lastJournalId) {
            $goodsIssue->journal_entry_id = $lastJournalId;
        }
        $goodsIssue->save();

        $goodsIssue->load('warehouse', 'costCenter', 'project', 'journalEntry');

        return response()->json([
            'data' => $this->payload($goodsIssue, true),
            'results' => $results,
        ]);
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function payload(GoodsIssue $doc, bool $withRelations = false): array
    {
        return [
            'id' => $doc->id,
            'company_id' => $doc->company_id,
            'number' => $doc->number,
            'warehouse_id' => $doc->warehouse_id,
            'reason' => $doc->reason,
            'issued_at' => $doc->issued_at?->toDateString(),
            'state' => $doc->state,
            'cost_center_id' => $doc->cost_center_id,
            'project_id' => $doc->project_id,
            'lines' => $doc->lines,
            'notes' => $doc->notes,
            'journal_entry_id' => $doc->journal_entry_id,
            'warehouse' => $withRelations && $doc->relationLoaded('warehouse') ? [
                'id' => $doc->warehouse?->id,
                'code' => $doc->warehouse?->code,
                'name' => $doc->warehouse?->name,
            ] : null,
            'cost_center' => $withRelations && $doc->relationLoaded('costCenter') ? [
                'id' => $doc->costCenter?->id,
                'code' => $doc->costCenter?->code,
                'name' => $doc->costCenter?->name,
            ] : null,
            'project' => $withRelations && $doc->relationLoaded('project') ? [
                'id' => $doc->project?->id,
                'code' => $doc->project?->code,
                'name' => $doc->project?->name,
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
