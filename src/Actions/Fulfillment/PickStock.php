<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Fulfillment;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\StockPicked;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\PickList;
use Headless\Accounting\Models\PickListLine;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseBin;
use Headless\Accounting\Models\WarehouseZone;
use Headless\Accounting\Support\Config;

/**
 * PickStock — registers that a {@see PickListLine} has been picked from
 * a specific bin. Decrements on_hand and writes a `pick` StockMovement.
 * The line state becomes `picked`, `short`, or `skipped` depending on
 * the requested vs. picked quantities.
 */
final class PickStock extends Action
{
    protected function handle(
        PickList $pickList,
        int $variantId,
        int $pickedQuantity,
        ?int $binId = null,
        ?string $pickerName = null,
        string $note = '',
    ): PickListLine {
        if ($pickedQuantity < 0) {
            throw new AccountingException('Picked quantity cannot be negative.');
        }

        $line = PickListLine::query()
            ->where('pick_list_id', $pickList->id)
            ->where('variant_id', $variantId)
            ->firstOrFail();

        if ($line->state === PickListLine::STATE_SKIPPED) {
            throw new AccountingException('Cannot pick a skipped line.');
        }

        $binId ??= $line->bin_id;

        $stockItem = StockItem::query()
            ->where('variant_id', $variantId)
            ->whereIn('location_id', function ($q) use ($pickList) {
                $q->select('location_id')->from((new Warehouse)->getTable())
                    ->where('id', $pickList->warehouse_id);
            })
            ->first();

        if (! $stockItem) {
            throw new AccountingException(
                "No stock item found for variant {$variantId} at warehouse {$pickList->warehouse_id}."
            );
        }

        if ($pickedQuantity > $stockItem->on_hand) {
            throw new AccountingException(
                "Cannot pick {$pickedQuantity} units; only {$stockItem->on_hand} on hand."
            );
        }

        $stockItem->on_hand = max(0, (int) $stockItem->on_hand - $pickedQuantity);
        $stockItem->save();

        StockMovement::create([
            'stock_item_id' => $stockItem->id,
            'reason' => 'pick',
            'quantity' => -$pickedQuantity,
            'balance_after' => $stockItem->on_hand,
            'source_type' => $pickList->getMorphClass(),
            'source_id' => $pickList->id,
            'occurred_at' => now(),
        ]);

        $line->quantity_picked = $pickedQuantity;
        $line->bin_id = $binId;
        $line->note = $note ?: null;
        $line->picked_at = now();
        $line->state = $pickedQuantity >= (int) $line->quantity_requested
            ? PickListLine::STATE_PICKED
            : PickListLine::STATE_SHORT;
        $line->save();

        if ($pickerName && ! $pickList->picker_name) {
            $pickList->picker_name = $pickerName;
        }

        if ($pickList->state === PickList::STATE_OPEN) {
            $pickList->state = PickList::STATE_PICKING;
            $pickList->started_at = now();
        }

        if ($pickList->isFullyPicked()) {
            $pickList->state = PickList::STATE_PICKED;
            $pickList->completed_at = now();
        }

        $pickList->save();

        $fresh = $line->fresh();

        StockPicked::dispatch($pickList, $fresh);

        return $fresh;
    }

    protected function resolveBin(int $warehouseId, int $variantId): ?WarehouseBin
    {
        return WarehouseBin::query()
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('active', true)
            ->orderBy('code')
            ->first();
    }

    protected function zone(int $warehouseId, string $kind): ?WarehouseZone
    {
        return WarehouseZone::query()
            ->where('warehouse_id', $warehouseId)
            ->where('kind', $kind)
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
