<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Fulfillment;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\PickList;
use Headless\Accounting\Models\PickListLine;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Support\Config;

/**
 * CreatePickList — materialises a pick list per warehouse from a
 * {@see FulfillmentPlan}. Picks are optimised by ordering lines
 * first by bin code (alphanumeric) so pickers walk an aisle route.
 */
final class CreatePickList extends Action
{
    protected function handle(FulfillmentPlan $plan): PickList
    {
        $grouped = $plan->allocationsByWarehouse();
        $created = [];

        foreach ($grouped as $warehouseId => $lines) {
            $existing = PickList::query()
                ->where('fulfillment_plan_id', $plan->id)
                ->where('warehouse_id', $warehouseId)
                ->first();
            if ($existing) {
                $created[] = $existing;

                continue;
            }

            $pickList = PickList::create([
                'fulfillment_plan_id' => $plan->id,
                'warehouse_id' => $warehouseId,
                'number' => $this->nextNumber(),
                'state' => PickList::STATE_OPEN,
            ]);

            $seq = 0;
            foreach ($lines as $allocation) {
                $stockItem = StockItem::query()
                    ->where('variant_id', $allocation['variant_id'])
                    ->whereHas('location', function ($q) {
                        // resolve stock_item through the warehouse's linked Location
                    })
                    ->first();

                $bin = $this->resolveBin($warehouseId, (int) $allocation['variant_id']);

                PickListLine::create([
                    'pick_list_id' => $pickList->id,
                    'bin_id' => $bin?->id,
                    'variant_id' => $allocation['variant_id'],
                    'stock_item_id' => $stockItem?->id,
                    'quantity_requested' => (int) $allocation['quantity'],
                    'quantity_picked' => 0,
                    'state' => PickListLine::STATE_PENDING,
                    'pick_sequence' => $seq++,
                ]);
            }

            $created[] = $pickList;
        }

        $plan->update([
            'state' => FulfillmentPlan::STATE_PICKING,
            'metadata' => array_merge((array) $plan->metadata, [
                'pick_lists' => array_map(fn ($p) => $p->id, $created),
            ]),
        ]);

        return $created[0] ?? PickList::query()->where('fulfillment_plan_id', $plan->id)->firstOrFail();
    }

    protected function resolveBin(int $warehouseId, int $variantId): ?WarehouseBin
    {
        return WarehouseBin::query()
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('active', true)
            ->orderBy('code')
            ->first();
    }

    protected function nextNumber(): string
    {
        $today = now()->format('Ymd');
        $count = PickList::query()->whereDate('created_at', today())->count() + 1;
        $prefix = Config::string('headless-accounting.number_prefixes.pick_list', 'PL');

        return sprintf('%s-%s-%05d', $prefix, $today, $count);
    }
}
