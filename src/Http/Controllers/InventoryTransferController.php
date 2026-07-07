<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Inventory\TransferStock;
use Headless\Accounting\Models\InventoryTransfer;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * InventoryTransferController — moves stock between locations.
 *
 * Draft → Posted. Lines are interpreted defensively because the
 * stored JSON may take one of several shapes (`variant_id` /
 * `quantity` / `unit_cost_minor`).
 */
class InventoryTransferController
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryTransfer::query()->with('fromLocation', 'toLocation');

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        if ($fromLocationId = $request->query('from_location_id')) {
            $query->where('from_location_id', (int) $fromLocationId);
        }

        if ($toLocationId = $request->query('to_location_id')) {
            $query->where('to_location_id', (int) $toLocationId);
        }

        $rows = $query->orderByDesc('id')->paginate();
        $data = $rows->getCollection()->map(fn (InventoryTransfer $t) => $this->payload($t))->all();

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

    public function show(InventoryTransfer $transfer): JsonResponse
    {
        $transfer->load('fromLocation', 'toLocation', 'journalEntry');

        return response()->json(['data' => $this->payload($transfer, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_location_id' => 'required|integer',
            'to_location_id' => 'required|integer|different:from_location_id',
            'transferred_at' => 'required|date',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|integer',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.from_bin_id' => 'nullable|integer',
            'lines.*.to_bin_id' => 'nullable|integer',
            'lines.*.batch_number' => 'nullable|string|max:64',
            'lines.*.batch_id' => 'nullable|integer',
            'lines.*.unit_cost_minor' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        $from = Location::query()->findOrFail($data['from_location_id']);
        $to = Location::query()->findOrFail($data['to_location_id']);

        $document = InventoryTransfer::create([
            'company_id' => $request->user()?->company_id,
            'number' => $this->generateNumber(Config::string('headless-accounting.number_prefixes.inventory_transfer', 'TR')),
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'transferred_at' => $data['transferred_at'],
            'state' => 'draft',
            'lines' => $data['lines'],
        ]);

        return response()->json(['data' => $this->payload($document->fresh(), true)], 201);
    }

    public function destroy(InventoryTransfer $transfer): JsonResponse
    {
        abort_unless($transfer->state === 'draft', 422, 'Only draft transfers can be deleted.');

        $transfer->delete();

        return response()->json(['deleted' => true]);
    }

    public function post(Request $request, InventoryTransfer $transfer): JsonResponse
    {
        abort_unless($transfer->state === 'draft', 422, 'Only draft transfers can be posted.');

        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
        ]);

        $currency = $data['currency'] ?? null;
        $from = Location::query()->findOrFail($transfer->from_location_id);
        $to = Location::query()->findOrFail($transfer->to_location_id);

        $results = [];
        $lines = $transfer->lines ?? [];
        foreach ($lines as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }

            $variant = ProductVariant::query()->findOrFail($variantId);

            $result = app(TransferStock::class)->execute(
                variant: $variant,
                from: $from,
                to: $to,
                quantity: $quantity,
                fromBinId: $line['from_bin_id'] ?? null,
                toBinId: $line['to_bin_id'] ?? null,
                batchNumber: $line['batch_number'] ?? null,
                source: $transfer,
                currency: $currency,
            );

            $results[] = [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'journal_entry_id' => $result['journal_entry_id'] ?? null,
            ];
        }

        $lastJournalId = collect($results)->pluck('journal_entry_id')->filter()->last();

        $transfer->state = 'posted';
        if ($lastJournalId) {
            $transfer->journal_entry_id = $lastJournalId;
        }
        $transfer->save();

        $transfer->load('fromLocation', 'toLocation', 'journalEntry');

        return response()->json([
            'data' => $this->payload($transfer, true),
            'results' => $results,
            'summary' => [
                'lines_posted' => count($results),
                'total_quantity' => array_sum(array_column($results, 'quantity')),
            ],
        ]);
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function payload(InventoryTransfer $doc, bool $withRelations = false): array
    {
        return [
            'id' => $doc->id,
            'company_id' => $doc->company_id,
            'number' => $doc->number,
            'from_location_id' => $doc->from_location_id,
            'to_location_id' => $doc->to_location_id,
            'transferred_at' => $doc->transferred_at?->toDateString(),
            'state' => $doc->state,
            'lines' => $doc->lines,
            'journal_entry_id' => $doc->journal_entry_id,
            'from_location' => $withRelations && $doc->relationLoaded('fromLocation') ? [
                'id' => $doc->fromLocation?->id,
                'code' => $doc->fromLocation?->code,
                'name' => $doc->fromLocation?->name,
            ] : null,
            'to_location' => $withRelations && $doc->relationLoaded('toLocation') ? [
                'id' => $doc->toLocation?->id,
                'code' => $doc->toLocation?->code,
                'name' => $doc->toLocation?->name,
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
