<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\DisposalOrder;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\StockWriteOff;
use Headless\Accounting\Support\Config;

/**
 * PostDisposalOrder — closes out a {@see DisposalOrder} by verifying
 * every linked {@see StockWriteOff} is approved and stamping a final
 * 'dispose' StockMovement per write-off line. The StockItem balances
 * were already decremented when each write-off was posted; this action
 * only confirms execution and finalises the disposal workflow.
 */
final class PostDisposalOrder extends Action
{
    protected function handle(DisposalOrder $order, ?string $currency = null): array
    {
        $currency ??= Config::string('headless-accounting.currency.default');

        if ($order->state === 'executed' || $order->state === 'cancelled') {
            throw new AccountingException(
                "Disposal order {$order->number} is already '{$order->state}'."
            );
        }

        $writeOffs = StockWriteOff::query()
            ->where('disposal_order_id', $order->id)
            ->get();

        $movements = [];
        foreach ($writeOffs as $writeOff) {
            if ($writeOff->state !== 'approved') {
                throw new AccountingException(
                    "Write-off {$writeOff->number} must be approved before disposal (state={$writeOff->state})."
                );
            }

            foreach ((array) $writeOff->lines as $line) {
                $variantId = $line['variant_id'] ?? null;
                if (! $variantId) {
                    continue;
                }

                $stockItem = StockItem::query()
                    ->where('variant_id', $variantId)
                    ->where('location_id', $writeOff->warehouse_id)
                    ->first();

                if (! $stockItem) {
                    continue;
                }

                $movement = StockMovement::create([
                    'stock_item_id' => $stockItem->id,
                    'reason' => 'dispose',
                    'quantity' => 0,
                    'balance_after' => $stockItem->on_hand,
                    'source_type' => $order->getMorphClass(),
                    'source_id' => $order->id,
                    'occurred_at' => now(),
                ]);

                $movements[] = $movement;
            }
        }

        $order->update([
            'state' => 'executed',
            'disposed_at' => now()->toDateString(),
        ]);

        return ['disposal_order' => $order->fresh(), 'movements' => $movements];
    }
}
