<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Inventory\PostWriteOff;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\StockWriteOff;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * StockWriteOffController — covers damaged/lost/expired stock.
 *
 * Lifecycle: `pending` → `approved` → `disposed`.
 * `approve()` is a no-GL state flip; `post()` runs
 * {@see PostWriteOff} (which writes the GL impact when a disposal
 * order is attached) and sets the document to `disposed`.
 */
class StockWriteOffController
{
    public function index(Request $request): JsonResponse
    {
        $query = StockWriteOff::query()->with('warehouse');

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($warehouseId = $request->query('warehouse_id')) {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        $rows = $query->orderByDesc('id')->paginate();
        $data = $rows->getCollection()->map(fn (StockWriteOff $w) => $this->payload($w))->all();

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

    public function show(StockWriteOff $stockWriteOff): JsonResponse
    {
        $stockWriteOff->load('warehouse', 'disposalOrder', 'journalEntry');

        return response()->json(['data' => $this->payload($stockWriteOff, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer',
            'category' => 'required|string|in:damaged,lost,expired,obsolete,stolen,recalled',
            'occurred_at' => 'required|date',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|integer',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.batch_id' => 'nullable|integer',
            'lines.*.bin_id' => 'nullable|integer',
            'lines.*.unit_cost_minor' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
            'disposal_order_id' => 'nullable|integer',
        ]);

        Location::query()->findOrFail($data['warehouse_id']);

        $document = StockWriteOff::create([
            'company_id' => $request->user()?->company_id,
            'warehouse_id' => $data['warehouse_id'],
            'number' => $this->generateNumber(Config::string('headless-accounting.number_prefixes.stock_write_off', 'WO')),
            'category' => $data['category'],
            'occurred_at' => $data['occurred_at'],
            'state' => 'pending',
            'lines' => $data['lines'],
            'notes' => $data['notes'] ?? null,
            'disposal_order_id' => $data['disposal_order_id'] ?? null,
        ]);

        return response()->json(['data' => $this->payload($document->fresh(), true)], 201);
    }

    public function destroy(StockWriteOff $stockWriteOff): JsonResponse
    {
        abort_unless($stockWriteOff->state === 'pending', 422, 'Only pending documents can be deleted.');

        $stockWriteOff->delete();

        return response()->json(['deleted' => true]);
    }

    public function approve(StockWriteOff $stockWriteOff): JsonResponse
    {
        abort_unless($stockWriteOff->state === 'pending', 422, 'Only pending documents can be approved.');

        $stockWriteOff->state = 'approved';
        $stockWriteOff->save();

        return response()->json(['data' => $this->payload($stockWriteOff->fresh(), true)]);
    }

    public function post(Request $request, StockWriteOff $stockWriteOff): JsonResponse
    {
        abort_unless(in_array($stockWriteOff->state, ['pending', 'approved'], true), 422, 'Document must be pending or approved to post.');

        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
        ]);

        $currency = $data['currency'] ?? null;

        $posted = app(PostWriteOff::class)->execute($stockWriteOff, $currency);

        $stockWriteOff->refresh()->load('warehouse', 'disposalOrder', 'journalEntry');

        return response()->json([
            'data' => $this->payload($stockWriteOff, true),
            'journal_entry_id' => $posted['journal_entry_id'] ?? $stockWriteOff->journal_entry_id,
        ]);
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function payload(StockWriteOff $doc, bool $withRelations = false): array
    {
        return [
            'id' => $doc->id,
            'company_id' => $doc->company_id,
            'number' => $doc->number,
            'warehouse_id' => $doc->warehouse_id,
            'category' => $doc->category,
            'occurred_at' => $doc->occurred_at?->toDateString(),
            'state' => $doc->state,
            'lines' => $doc->lines,
            'notes' => $doc->notes,
            'disposal_order_id' => $doc->disposal_order_id,
            'journal_entry_id' => $doc->journal_entry_id,
            'warehouse' => $withRelations && $doc->relationLoaded('warehouse') ? [
                'id' => $doc->warehouse?->id,
                'code' => $doc->warehouse?->code,
                'name' => $doc->warehouse?->name,
            ] : null,
            'disposal_order' => $withRelations && $doc->relationLoaded('disposalOrder') ? [
                'id' => $doc->disposalOrder?->id,
                'number' => $doc->disposalOrder?->number,
                'state' => $doc->disposalOrder?->state,
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
