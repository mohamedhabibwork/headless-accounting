<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Models\Invoice;

class InvoiceIssued extends Event
{
    public function __construct(public readonly Invoice $invoice) {}
}
