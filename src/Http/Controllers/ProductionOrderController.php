<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Inventory\MaterialIssue;
use Headless\Accounting\Actions\Inventory\PostProductionOutput;
use Headless\Accounting\Models\Bom;
use Headless\Accounting\Models\ProductionOrder;
use Headless\Accounting\Support\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProductionOrderController — drives a BOM-based production run
 * through its lifecycle: planned → in_progress → completed.
 *
 *  - `consume()` issues raw materials via {@see MaterialIssue}
 *  - `complete()` records produced output via {@see PostProductionOutput}
 */
class ProductionOrderController
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductionOrder::query()->with('bom');

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        if ($bomId = $request->query('bom_id')) {
            $query->where('bom_id', (int) $bomId);
        }

        $rows = $query->orderByDesc('id')->paginate();
        $data = $rows->getCollection()->map(fn (ProductionOrder $p) => $this->payload($p))->all();

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

    public function show(ProductionOrder $productionOrder): JsonResponse
    {
        $productionOrder->load('bom.lines.component', 'journalEntry');

        return response()->json(['data' => $this->payload($productionOrder, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bom_id' => 'required|integer',
            'quantity_to_produce' => 'required|integer|min:1',
            'scheduled_date' => 'nullable|date',
        ]);

        $bom = Bom::query()->findOrFail($data['bom_id']);

        $order = ProductionOrder::create([
            'company_id' => $request->user()?->company_id,
            'number' => $this->generateNumber(Config::string('headless-accounting.number_prefixes.production_order', 'PROD')),
            'bom_id' => $bom->id,
            'quantity_to_produce' => (int) $data['quantity_to_produce'],
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'state' => 'planned',
        ]);

        return response()->json(['data' => $this->payload($order, true)], 201);
    }

    public function update(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        abort_unless(in_array($productionOrder->state, ['planned', 'in_progress'], true), 422, 'Production order cannot be updated in its current state.');

        $data = $request->validate([
            'quantity_to_produce' => 'sometimes|integer|min:1',
            'scheduled_date' => 'sometimes|nullable|date',
        ]);

        $productionOrder->update($data);

        return response()->json(['data' => $this->payload($productionOrder->fresh(), true)]);
    }

    public function destroy(ProductionOrder $productionOrder): JsonResponse
    {
        abort_unless($productionOrder->state === 'planned', 422, 'Only planned production orders can be deleted.');

        $productionOrder->delete();

        return response()->json(['deleted' => true]);
    }

    public function consume(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        abort_unless($productionOrder->state === 'planned', 422, 'Only planned production orders can be consumed.');

        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
        ]);

        $result = app(MaterialIssue::class)->execute($productionOrder, $data['currency'] ?? null);

        $productionOrder->state = 'in_progress';
        $productionOrder->save();

        return response()->json([
            'data' => $this->payload($productionOrder->fresh(), true),
            'consumption' => $result,
        ]);
    }

    public function complete(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        abort_unless($productionOrder->state === 'in_progress', 422, 'Production order must be in progress to complete.');

        $data = $request->validate([
            'output_bin_id' => 'nullable|integer',
            'currency' => 'nullable|string|size:3',
        ]);

        $result = app(PostProductionOutput::class)->execute(
            $productionOrder,
            isset($data['output_bin_id']) ? (int) $data['output_bin_id'] : null,
            $data['currency'] ?? null,
        );

        $productionOrder->state = 'completed';
        $productionOrder->completed_at = now();
        if (! empty($result['journal_entry_id'])) {
            $productionOrder->journal_entry_id = (int) $result['journal_entry_id'];
        }
        $productionOrder->save();

        $productionOrder->load('bom.lines.component', 'journalEntry');

        return response()->json([
            'data' => $this->payload($productionOrder, true),
            'output' => $result,
        ]);
    }

    private function generateNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function payload(ProductionOrder $order, bool $withRelations = false): array
    {
        return [
            'id' => $order->id,
            'company_id' => $order->company_id,
            'number' => $order->number,
            'bom_id' => $order->bom_id,
            'quantity_to_produce' => (int) $order->quantity_to_produce,
            'scheduled_date' => $order->scheduled_date?->toDateString(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'state' => $order->state,
            'journal_entry_id' => $order->journal_entry_id,
            'bom' => $withRelations && $order->relationLoaded('bom') ? [
                'id' => $order->bom?->id,
                'code' => $order->bom?->code,
                'name' => $order->bom?->name,
                'lines' => $order->bom?->relationLoaded('lines') ? $order->bom->lines->map(fn ($l) => [
                    'id' => $l->id,
                    'component_variant_id' => $l->component_variant_id,
                    'quantity' => (float) $l->quantity,
                ])->all() : null,
            ] : null,
            'journal_entry' => $withRelations && $order->relationLoaded('journalEntry') ? [
                'id' => $order->journalEntry?->id,
                'number' => $order->journalEntry?->number,
                'state' => $order->journalEntry?->state,
            ] : null,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}
