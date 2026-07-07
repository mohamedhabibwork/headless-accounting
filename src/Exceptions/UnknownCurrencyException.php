<?php

declare(strict_types=1);

namespace Headless\Accounting\Exceptions;

class UnknownCurrencyException extends AccountingException
{
    public static function for(string $code): self
    {
        return new self("Unknown currency code: {$code}");
    }
}
