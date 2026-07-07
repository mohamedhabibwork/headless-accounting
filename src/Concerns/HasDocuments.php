<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Documents\DocumentService;
use Headless\Accounting\Models\DocumentAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasDocuments — drop-in trait so a host-side model can attach files
 * to the package's polymorphic `DocumentAttachment` table. Pairs with
 * {@see DocumentService}.
 *
 * Schema expected: none — uses polymorphism keyed on the model's
 * primary key.
 *
 * @mixin Model
 */
trait HasDocuments
{
    /** @return MorphMany<DocumentAttachment> */
    public function documents(): MorphMany
    {
        return $this->morphMany(
            DocumentAttachment::class,
            'subject'
        );
    }

    /** Convenience: latest attachment of a given kind (e.g. 'invoice_pdf'). */
    public function latestDocument(string $kind): ?DocumentAttachment
    {
        return $this->documents()
            ->where('extra_metadata->kind', $kind)
            ->orderByDesc('id')
            ->first();
    }
}
