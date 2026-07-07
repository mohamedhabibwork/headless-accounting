<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Inventory\BatchService;

/**
 * QuarantineExpiredStock — action wrapper around
 * {@see BatchService::quarantineExpiredBatches()}. Returns the number of
 * batches transitioned to the 'expired' (and optionally 'quarantined')
 * state during the sweep.
 */
final class QuarantineExpiredStock extends Action
{
    public function __construct(private readonly BatchService $batches) {}

    protected function handle(?int $limit = 500): int
    {
        return $this->batches->quarantineExpiredBatches();
    }
}
