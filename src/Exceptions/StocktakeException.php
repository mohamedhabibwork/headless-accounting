<?php

declare(strict_types=1);

namespace Headless\Accounting\Exceptions;

class StocktakeException extends AccountingException
{
    public static function uncountedLines(int $count): self
    {
        return new self("Stocktake has {$count} uncounted lines.");
    }

    public static function alreadyPosted(string $number): self
    {
        return new self("Stocktake {$number} is already posted.");
    }

    public static function invalidTransition(string $number, string $from, string $to): self
    {
        return new self("Stocktake {$number} cannot transition from {$from} to {$to}.");
    }
}
