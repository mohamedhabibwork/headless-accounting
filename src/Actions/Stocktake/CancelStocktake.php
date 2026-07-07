<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Stocktake;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Stocktake;

/**
 * CancelStocktake — marks the stocktake cancelled. Only allowed from
 * non-terminal states; once `posted` it cannot be cancelled (use a
 * reversing adjustment instead).
 */
final class CancelStocktake extends Action
{
    protected function handle(Stocktake $stocktake, ?string $reason = null): Stocktake
    {
        if ($stocktake->state === Stocktake::STATE_POSTED) {
            throw new AccountingException(
                "Stocktake {$stocktake->number} is posted; cancellation is not allowed."
            );
        }

        $stocktake->state = Stocktake::STATE_CANCELLED;
        if ($reason) {
            $stocktake->notes = trim(($stocktake->notes ? $stocktake->notes."\n" : '').'CANCELLED: '.$reason);
        }
        $stocktake->save();

        return $stocktake;
    }
}
