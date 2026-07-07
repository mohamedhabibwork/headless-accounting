<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;

/**
 * AdjustStock — bring stock items up or down by a delta, writing a
 * corresponding StockMovement for auditability.
 *
 * Used by:
 *   - receiving purchases
 *   - manual inventory counts
 *   - returns
 */
final class AdjustStock extends Action
{
    protected function handle(
        ProductVariant $variant,
        Location $location,
        int $delta,
        string $reason = 'adjust',
        mixed $source = null,
    ): StockMovement {
        $item = StockItem::query()->firstOrCreate(
            ['variant_id' => $variant->id, 'location_id' => $location->id],
            ['on_hand' => 0, 'reserved' => 0, 'incoming' => 0],
        );

        $item->on_hand = max(0, (int) $item->on_hand + $delta);
        $item->save();

        return StockMovement::create([
            'stock_item_id' => $item->id,
            'reason' => $reason,
            'quantity' => $delta,
            'balance_after' => $item->on_hand,
            'source_type' => $source?->getMorphClass() ?? $variant->getMorphClass(),
            'source_id' => $source?->getKey() ?? $variant->getKey(),
            'occurred_at' => now(),
        ]);
    }
}
