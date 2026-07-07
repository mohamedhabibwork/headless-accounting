<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Stocktake;
use Illuminate\Database\Eloquent\Model;

/**
 * StocktakePosted — fired after a stocktake successfully posts.
 * Carries the source stocktake and the journal entry that was created.
 */
class StocktakePosted extends Event
{
    public function __construct(
        public readonly Stocktake $stocktake,
        public readonly ?Model $journalEntry = null,
    ) {}
}
