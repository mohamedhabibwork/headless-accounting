<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Models\Invoice;

/**
 * InvoiceRenderer — extension of {@see InvoiceRenderable} that
 * supports multiple output formats ("pdf", "html", "ubl") and a
 * richer rendering context. It is the recommended contract for
 * hosts that build print-ready invoices.
 *
 *   app(InvoiceRenderer::class)->render($invoice, 'pdf', 'fr-FR');
 *
 * Or, when implementing on a host-side renderer:
 *
 *   class MyPdfInvoiceRenderer implements InvoiceRenderer { … }
 *   config(['headless-accounting.renderers.invoice' => MyPdfInvoiceRenderer::class]);
 */
interface InvoiceRenderer
{
    /**
     * @param  string  $format  Output format ("pdf", "html", "ubl", "json", …).
     * @param  string  $locale  BCP-47 locale tag.
     */
    public function render(Invoice $invoice, string $format, string $locale): string;

    /** Whether this renderer supports the requested format. */
    public function supportsFormat(string $format): bool;
}
