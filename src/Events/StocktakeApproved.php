<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Stocktake;

/**
 * StocktakeApproved — fired when a stocktake is approved; precedes the
 * posting side-effects.
 */
class StocktakeApproved extends Event
{
    public function __construct(public readonly Stocktake $stocktake) {}
}
