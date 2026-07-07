<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Inventory\PostDisposalOrder;
use Headless\Accounting\Models\DisposalOrder;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DisposalOrderController — header for the disposal/scrap workflow.
 *
 * `execute()` runs {@see PostDisposalOrder} which carries out the
 * GL + downstream side-effects for the linked StockWriteOffs.
 */
class DisposalOrderController
{
    public function index(Request $request): JsonResponse
    {
        $query = DisposalOrder::query();

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }

        $rows = $query->orderByDesc('id')->paginate();
        $data = $rows->getCollection()->map(fn (DisposalOrder $d) => $this->payload($d))->all();

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

    public function show(DisposalOrder $disposalOrder): JsonResponse
    {
        $disposalOrder->load('writeOffs', 'journalEntry');

        return response()->json(['data' => $this->payload($disposalOrder, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'method' => 'required|string|in:scrap,recycle,return_to_vendor,donate,destroy,sell',
            'disposed_at' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        $document = DisposalOrder::create([
            'company_id' => $request->user()?->company_id,
            'number' => $this->generateNumber(Config::string('headless-accounting.number_prefixes.disposal_order', 'DSP')),
            'method' => $data['method'],
            'disposed_at' => $data['disposed_at'] ?? null,
            'state' => 'draft',
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $this->payload($document, true)], 201);
    }

    public function update(Request $request, DisposalOrder $disposalOrder): JsonResponse
    {
        abort_unless($disposalOrder->state === 'draft', 422, 'Only draft disposal orders can be updated.');

        $data = $request->validate([
            'method' => 'sometimes|string|in:scrap,recycle,return_to_vendor,donate,destroy,sell',
            'disposed_at' => 'sometimes|nullable|date',
            'reason' => 'sometimes|nullable|string|max:500',
            'notes' => 'sometimes|nullable|string|max:500',
        ]);

        $disposalOrder->update($data);

        return response()->json(['data' => $this->payload($disposalOrder->fresh(), true)]);
    }

    public function execute(Request $request, DisposalOrder $disposalOrder): JsonResponse
    {
        abort_unless(in_array($disposalOrder->state, ['draft', 'approved'], true), 422, 'Document must be draft or approved to execute.');

        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
        ]);

        $executed = app(PostDisposalOrder::class)->execute($disposalOrder, $data['currency'] ?? null);

        $disposalOrder->refresh()->load('writeOffs', 'journalEntry');

        return response()->json([
            'data' => $this->payload($disposalOrder, true),
            'journal_entry_id' => $executed['journal_entry_id'] ?? $disposalOrder->journal_entry_id,
        ]);
    }

    public function destroy(DisposalOrder $disposalOrder): JsonResponse
    {
        abort_unless($disposalOrder->state === 'draft', 422, 'Only draft disposal orders can be deleted.');

        $disposalOrder->delete();

        return response()->json(['deleted' => true]);
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function payload(DisposalOrder $doc, bool $withRelations = false): array
    {
        return [
            'id' => $doc->id,
            'company_id' => $doc->company_id,
            'number' => $doc->number,
            'method' => $doc->method,
            'disposed_at' => $doc->disposed_at?->toDateString(),
            'state' => $doc->state,
            'reason' => $doc->reason,
            'notes' => $doc->notes,
            'journal_entry_id' => $doc->journal_entry_id,
            'write_offs' => $withRelations && $doc->relationLoaded('writeOffs') ? $doc->writeOffs->map(fn ($w) => [
                'id' => $w->id,
                'number' => $w->number,
                'category' => $w->category,
                'state' => $w->state,
            ])->all() : null,
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
