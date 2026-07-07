<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Stocktake;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\StocktakeCreated;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\StocktakeLine;
use Headless\Accounting\Models\Warehouse;

/**
 * CreateStocktake — opens a new stocktake for a warehouse. Pre-populates
 * lines from the current {@see StockItem} balances so counters have a
 * list to walk. Restricted scopes (zone / variant) filter the
 * pre-population.
 */
final class CreateStocktake extends Action
{
    protected function handle(
        Warehouse $warehouse,
        string $scope = Stocktake::SCOPE_FULL,
        ?array $variantIds = null,
        ?array $zoneIds = null,
        ?string $scheduledAt = null,
        ?string $notes = null,
    ): Stocktake {
        $stocktake = Stocktake::create([
            'warehouse_id' => $warehouse->id,
            'number' => $this->nextNumber(),
            'state' => Stocktake::STATE_DRAFT,
            'scope' => $scope,
            'scheduled_at' => $scheduledAt,
            'zones' => $zoneIds,
            'variants' => $variantIds,
            'notes' => $notes,
        ]);

        $this->prepopulateLines($stocktake, $warehouse, $scope, $variantIds, $zoneIds);

        $stocktake->load('lines');

        StocktakeCreated::dispatch($stocktake);

        return $stocktake;
    }

    protected function prepopulateLines(
        Stocktake $stocktake,
        Warehouse $warehouse,
        string $scope,
        ?array $variantIds,
        ?array $zoneIds,
    ): void {
        if (! $warehouse->location_id) {
            return;
        }

        $query = StockItem::query()
            ->where('location_id', $warehouse->location_id)
            ->where('on_hand', '>', 0);

        if ($scope === Stocktake::SCOPE_VARIANT && ! empty($variantIds)) {
            $query->whereIn('variant_id', $variantIds);
        }

        $items = $query->get();
        $now = now();

        foreach ($items as $item) {
            if ($scope === Stocktake::SCOPE_ZONE && ! empty($zoneIds)) {
                $firstBin = $warehouse->bins()
                    ->whereIn('zone_id', $zoneIds)
                    ->first();
                if (! $firstBin) {
                    continue;
                }
            }

            StocktakeLine::create([
                'stocktake_id' => $stocktake->id,
                'variant_id' => $item->variant_id,
                'bin_id' => null,
                'system_quantity' => (int) $item->on_hand,
                'counted_quantity' => null,
                'variance' => 0,
                'state' => StocktakeLine::STATE_PENDING,
                'count_round' => 1,
                'counted_at' => null,
            ]);
        }
    }

    protected function nextNumber(): string
    {
        $today = now()->format('Ymd');
        $count = Stocktake::query()->whereDate('created_at', today())->count() + 1;

        return sprintf('ST-%s-%05d', $today, $count);
    }
}
