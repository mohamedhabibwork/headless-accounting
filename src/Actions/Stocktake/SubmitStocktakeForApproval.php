<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Stocktake;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Stocktake;

/**
 * SubmitStocktakeForApproval — moves the stocktake from `counted` to
 * `under_review`. A supervisor now has the green light to inspect the
 * variances and either approve (→ `approved`) or send the stocktake
 * back for a recount (→ `counting`).
 */
final class SubmitStocktakeForApproval extends Action
{
    protected function handle(Stocktake $stocktake): Stocktake
    {
        if (! in_array($stocktake->state, [Stocktake::STATE_COUNTED, Stocktake::STATE_COUNTING], true)) {
            throw new AccountingException(
                "Stocktake cannot be submitted from state {$stocktake->state}."
            );
        }

        $stocktake->state = Stocktake::STATE_UNDER_REVIEW;
        $stocktake->counted_at = $stocktake->counted_at ?: now()->toDateString();
        $stocktake->save();

        return $stocktake;
    }
}
