<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Contracts\InvoiceRenderer;
use Headless\Accounting\Models\Invoice;

class FakeInvoiceRenderer implements InvoiceRenderer
{
    public function render(Invoice $invoice, string $format, string $locale): string
    {
        return "FAKE-{$format}-{$locale}-{$invoice->number}";
    }

    public function supportsFormat(string $format): bool
    {
        return in_array($format, ['pdf', 'html'], true);
    }
}
