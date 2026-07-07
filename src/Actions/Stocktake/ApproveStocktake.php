<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Stocktake;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\StocktakeApproved;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Stocktake;

/**
 * ApproveStocktake — flips the stocktake into `approved` state,
 * optionally accepting a supervisor review comment. Variances are
 * frozen so a recount cannot silently shift them before posting.
 */
final class ApproveStocktake extends Action
{
    protected function handle(Stocktake $stocktake, ?int $approvedBy = null, ?string $notes = null): Stocktake
    {
        if (! in_array($stocktake->state, [Stocktake::STATE_COUNTED, Stocktake::STATE_UNDER_REVIEW], true)) {
            throw new AccountingException(
                "Stocktake {$stocktake->number} cannot be approved from state {$stocktake->state}."
            );
        }

        $pending = $stocktake->lines()->whereNull('counted_quantity')->count();
        if ($pending > 0) {
            throw new AccountingException(
                "Stocktake has {$pending} uncounted lines; recount or mark them as skipped before approval."
            );
        }

        $stocktake->state = Stocktake::STATE_APPROVED;
        $stocktake->approved_at = now()->toDateString();
        $stocktake->approved_by = $approvedBy;
        if ($notes) {
            $stocktake->notes = trim(($stocktake->notes ? $stocktake->notes."\n" : '').'APPROVAL: '.$notes);
        }
        $stocktake->save();

        StocktakeApproved::dispatch($stocktake);

        return $stocktake;
    }
}
