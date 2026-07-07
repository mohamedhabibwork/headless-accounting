<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Actions\Tax\UpsertTaxRate;
use Headless\Accounting\Actions\Tax\UpsertTaxZone;
use Headless\Accounting\Http\Requests\StoreTaxClassRequest;
use Headless\Accounting\Http\Requests\StoreTaxRateRequest;
use Headless\Accounting\Http\Requests\UpsertTaxZoneRequest;
use Headless\Accounting\Models\TaxClass;
use Headless\Accounting\Models\TaxRate;
use Headless\Accounting\Models\TaxZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TaxController extends Controller
{
    // ----- Zones -----

    public function indexZones(): JsonResponse
    {
        $zones = TaxZone::query()->with(['members', 'rates'])->orderBy('code')->paginate();

        return new JsonResponse([
            'data' => $zones->items(),
            'meta' => [
                'current_page' => $zones->currentPage(),
                'per_page' => $zones->perPage(),
                'total' => $zones->total(),
                'last_page' => $zones->lastPage(),
            ],
        ]);
    }

    public function showZone(int $zoneId): JsonResponse
    {
        $zone = TaxZone::query()->with(['members', 'rates'])->findOrFail($zoneId);

        return new JsonResponse([
            'id' => $zone->id,
            'code' => $zone->code,
            'name' => $zone->name,
            'description' => $zone->description,
            'active' => $zone->active,
            'members' => $zone->members->map(fn ($m) => [
                'id' => $m->id,
                'country_code' => $m->country_code,
                'region' => $m->region,
                'postal_code_pattern' => $m->postal_code_pattern,
                'operator' => $m->operator,
            ])->all(),
            'rates' => $zone->rates->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'percent' => $r->percent,
                'compound' => $r->compound,
                'priority' => $r->priority,
                'active' => $r->active,
                'tax_class_id' => $r->tax_class_id,
            ])->all(),
        ]);
    }

    public function upsertZone(UpsertTaxZoneRequest $request, UpsertTaxZone $upsert): JsonResponse
    {
        $zone = $upsert->execute(
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            members: (array) $request->validated('members', []),
            description: $request->validated('description'),
            active: (bool) $request->boolean('active', true),
        );

        return new JsonResponse([
            'id' => $zone->id,
            'code' => $zone->code,
            'name' => $zone->name,
        ], 201);
    }

    public function destroyZone(int $zoneId): JsonResponse
    {
        TaxZone::query()->findOrFail($zoneId)->delete();

        return new JsonResponse(['ok' => true], 204);
    }

    // ----- Classes -----

    public function indexClasses(): JsonResponse
    {
        $classes = TaxClass::query()->with('rates')->orderBy('name')->paginate();

        return new JsonResponse([
            'data' => $classes->items(),
            'meta' => [
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
                'last_page' => $classes->lastPage(),
            ],
        ]);
    }

    public function showClass(int $classId): JsonResponse
    {
        $class = TaxClass::query()->with('rates')->findOrFail($classId);

        return new JsonResponse([
            'id' => $class->id,
            'name' => $class->name,
            'slug' => $class->slug,
            'description' => $class->description,
        ]);
    }

    public function storeClass(StoreTaxClassRequest $request): JsonResponse
    {
        $class = TaxClass::query()->create($request->validated());

        return new JsonResponse([
            'id' => $class->id,
            'name' => $class->name,
            'slug' => $class->slug,
        ], 201);
    }

    public function destroyClass(int $classId): JsonResponse
    {
        TaxClass::query()->findOrFail($classId)->delete();

        return new JsonResponse(['ok' => true], 204);
    }

    // ----- Rates -----

    public function indexRates(): JsonResponse
    {
        $rates = TaxRate::query()->with(['zone', 'taxClass'])->orderBy('priority')->paginate();

        return new JsonResponse([
            'data' => $rates->items(),
            'meta' => [
                'current_page' => $rates->currentPage(),
                'per_page' => $rates->perPage(),
                'total' => $rates->total(),
                'last_page' => $rates->lastPage(),
            ],
        ]);
    }

    public function storeRate(StoreTaxRateRequest $request, UpsertTaxRate $upsert): JsonResponse
    {
        $zone = TaxZone::query()->findOrFail((int) $request->validated('zone_id'));
        $taxClass = $request->filled('tax_class_id')
            ? TaxClass::query()->findOrFail((int) $request->validated('tax_class_id'))
            : null;

        $rate = $upsert->execute(
            zone: $zone,
            name: (string) $request->validated('name'),
            percent: (float) $request->validated('percent'),
            taxClass: $taxClass,
            compound: (bool) $request->boolean('compound', false),
            priority: (int) $request->input('priority', 1),
            active: (bool) $request->boolean('active', true),
        );

        return new JsonResponse([
            'id' => $rate->id,
            'name' => $rate->name,
            'percent' => $rate->percent,
            'zone_id' => $rate->zone_id,
        ], 201);
    }

    public function destroyRate(int $rateId): JsonResponse
    {
        TaxRate::query()->findOrFail($rateId)->delete();

        return new JsonResponse(['ok' => true], 204);
    }
}
