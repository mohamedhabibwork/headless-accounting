<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Stocktake;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\StocktakeLine;

/**
 * RecordCount — registers a counter's count for a single
 * {@see StocktakeLine}. Calculates the variance against the system
 * quantity and persists the line. Repeated counts on the same
 * (variant, bin, round) overwrite each other; recounts bump the
 * `count_round` counter.
 */
final class RecordCount extends Action
{
    protected function handle(
        Stocktake $stocktake,
        int $variantId,
        int $countedQuantity,
        ?int $binId = null,
        ?int $counterId = null,
        ?string $reason = null,
        bool $recount = false,
    ): StocktakeLine {
        if ($countedQuantity < 0) {
            throw new AccountingException('Counted quantity cannot be negative.');
        }

        if (! in_array($stocktake->state, [Stocktake::STATE_DRAFT, Stocktake::STATE_COUNTING, Stocktake::STATE_UNDER_REVIEW], true)) {
            throw new AccountingException(
                "Cannot record counts on a stocktake in state {$stocktake->state}."
            );
        }

        $existing = StocktakeLine::query()
            ->where('stocktake_id', $stocktake->id)
            ->where('variant_id', $variantId)
            ->where('bin_id', $binId)
            ->orderByDesc('count_round')
            ->first();

        if ($existing && $recount) {
            $line = StocktakeLine::create([
                'stocktake_id' => $stocktake->id,
                'variant_id' => $variantId,
                'bin_id' => $binId,
                'system_quantity' => $existing->system_quantity,
                'counted_quantity' => $countedQuantity,
                'state' => StocktakeLine::STATE_COUNTED,
                'count_round' => (int) $existing->count_round + 1,
                'reason' => $reason,
                'counter_id' => $counterId,
                'counted_at' => now(),
            ]);
        } else {
            $line = $existing;
            if (! $line) {
                $line = StocktakeLine::create([
                    'stocktake_id' => $stocktake->id,
                    'variant_id' => $variantId,
                    'bin_id' => $binId,
                    'system_quantity' => 0,
                    'state' => StocktakeLine::STATE_PENDING,
                    'count_round' => 1,
                ]);
            }
            $line->counted_quantity = $countedQuantity;
            $line->reason = $reason;
            $line->counter_id = $counterId;
            $line->counted_at = now();
            $line->state = StocktakeLine::STATE_COUNTED;
            $line->save();
        }

        $line->variance = (int) $line->counted_quantity - (int) $line->system_quantity;
        $line->save();

        if ($stocktake->state === Stocktake::STATE_DRAFT) {
            $stocktake->state = Stocktake::STATE_COUNTING;
            $stocktake->save();
        }

        return $line;
    }
}
