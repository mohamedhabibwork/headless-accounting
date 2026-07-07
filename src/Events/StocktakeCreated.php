<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Stocktake;

/**
 * StocktakeCreated — fired when a new stocktake is opened.
 */
class StocktakeCreated extends Event
{
    public function __construct(public readonly Stocktake $stocktake) {}
}
