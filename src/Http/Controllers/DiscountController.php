<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Discount\CreateDiscount;
use Headless\Accounting\Http\Requests\StoreDiscountRequest;
use Headless\Accounting\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DiscountController extends Controller
{
    public function index(): JsonResponse
    {
        $discounts = Discount::query()
            ->with(['conditions', 'limitations'])
            ->orderByDesc('id')
            ->paginate();

        return new JsonResponse([
            'data' => $discounts->items(),
            'meta' => [
                'current_page' => $discounts->currentPage(),
                'per_page' => $discounts->perPage(),
                'total' => $discounts->total(),
                'last_page' => $discounts->lastPage(),
            ],
        ]);
    }

    public function show(int $discountId): JsonResponse
    {
        $discount = Discount::query()
            ->with(['conditions', 'limitations', 'targets', 'usages'])
            ->findOrFail($discountId);

        return new JsonResponse($this->serialize($discount));
    }

    public function store(StoreDiscountRequest $request, CreateDiscount $create): JsonResponse
    {
        $discount = $create->execute(
            name: (string) $request->validated('name'),
            type: (string) $request->validated('type'),
            config: (array) $request->validated('config', []),
            active: (bool) $request->boolean('active', true),
            stackable: (bool) $request->boolean('stackable', true),
            priority: (int) $request->input('priority', 100),
            code: $request->validated('code'),
            startsAt: $request->validated('starts_at'),
            endsAt: $request->validated('ends_at'),
            channelCode: $request->validated('channel_code'),
            targets: (array) $request->validated('targets', []),
            conditions: (array) $request->validated('conditions', []),
            limitations: (array) $request->validated('limitations', []),
        );

        return new JsonResponse($this->serialize($discount), 201);
    }

    public function update(int $discountId): JsonResponse
    {
        // Discounts are typically rotated for back-office integrity, so we
        // leave the record but expose a 200/501 signalling "see POST".
        $discount = Discount::query()->findOrFail($discountId);

        return new JsonResponse([
            'note' => 'Discounts are not mutated in place. POST a new discount and deactivate this one.',
            'discount' => $this->serialize($discount),
        ], 200);
    }

    public function destroy(int $discountId): JsonResponse
    {
        $discount = Discount::query()->findOrFail($discountId);
        $discount->update(['active' => false]);

        return new JsonResponse(['id' => $discount->id, 'active' => false]);
    }

    private function serialize(Discount $discount): array
    {
        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'code' => $discount->code,
            'type' => $discount->type,
            'active' => $discount->active,
            'stackable' => $discount->stackable,
            'priority' => $discount->priority,
            'config' => $discount->config,
            'channel_code' => $discount->channel_code,
            'starts_at' => $discount->starts_at?->toIso8601String(),
            'ends_at' => $discount->ends_at?->toIso8601String(),
            'conditions' => $discount->conditions->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'config' => $c->config,
            ])->all(),
            'limitations' => $discount->limitations->map(fn ($l) => [
                'id' => $l->id,
                'type' => $l->type,
                'config' => $l->config,
            ])->all(),
            'targets' => $discount->targets->map(fn ($t) => [
                'id' => $t->id,
                'target_type' => $t->target_type,
                'target_id' => $t->target_id,
            ])->all(),
        ];
    }
}
