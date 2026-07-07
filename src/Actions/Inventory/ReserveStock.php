<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockReservation;

final class ReserveStock extends Action
{
    protected function handle(
        ProductVariant $variant,
        Location $location,
        int $quantity,
        mixed $source = null,
    ): StockReservation {
        $item = StockItem::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        if ($item->available() < $quantity) {
            throw new AccountingException('Insufficient stock.');
        }

        $item->reserved = (int) $item->reserved + $quantity;
        $item->save();

        return StockReservation::create([
            'stock_item_id' => $item->id,
            'source_type' => $source?->getMorphClass() ?? $variant->getMorphClass(),
            'source_id' => $source?->getKey() ?? $variant->getKey(),
            'quantity' => $quantity,
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}
