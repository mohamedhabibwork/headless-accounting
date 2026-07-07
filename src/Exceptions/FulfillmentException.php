<?php

declare(strict_types=1);

namespace Headless\Accounting\Exceptions;

class FulfillmentException extends AccountingException
{
    public static function insufficientStock(string $sku, int $requested, int $available): self
    {
        return new self("Insufficient stock for SKU {$sku}: requested {$requested}, available {$available}.");
    }

    public static function noWarehouse(string $reason = ''): self
    {
        return new self('No warehouse could fulfill the order.'.($reason ? ' '.$reason : ''));
    }

    public static function unknownCarrier(string $code): self
    {
        return new self("Unknown carrier code: {$code}");
    }
}
