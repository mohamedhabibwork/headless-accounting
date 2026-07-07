<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Models\Invoice;

interface InvoiceRenderable
{
    public function render(Invoice $invoice, string $locale): string;
}
