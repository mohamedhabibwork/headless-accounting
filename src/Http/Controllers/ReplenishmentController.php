<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Inventory\ReplenishmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * ReplenishmentController — wraps the {@see ReplenishmentService}
 * to expose proposals, draft purchase-order generation, and a
 * per-variant warehouse proposal lookup.
 */
class ReplenishmentController
{
    public function proposals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|integer',
            'warehouse_id' => 'nullable|integer',
        ]);

        $proposals = app(ReplenishmentService::class)->proposals(
            companyId: (int) $data['company_id'],
            warehouseId: $data['warehouse_id'] ?? null,
        );

        return response()->json([
            'company_id' => (int) $data['company_id'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'count' => is_iterable($proposals) ? count($proposals) : (is_array($proposals) ? count($proposals) : 0),
            'data' => $proposals instanceof Collection
                ? $proposals->values()->all()
                : (array) $proposals,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|integer',
        ]);

        $count = app(ReplenishmentService::class)->generateDraftPurchaseOrders((int) $data['company_id']);
        $proposals = app(ReplenishmentService::class)->proposals((int) $data['company_id']);

        return response()->json([
            'company_id' => (int) $data['company_id'],
            'count' => (int) $count,
            'proposals' => $proposals instanceof Collection
                ? $proposals->values()->all()
                : (array) $proposals,
        ], 201);
    }

    public function proposalForVariant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
        ]);

        $proposal = app(ReplenishmentService::class)->proposalForVariant(
            (int) $data['variant_id'],
            (int) $data['warehouse_id'],
        );

        return response()->json([
            'variant_id' => (int) $data['variant_id'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'data' => $proposal,
        ]);
    }
}
