<?php

declare(strict_types=1);

namespace Headless\Accounting\Documents;

use Headless\Accounting\Models\DocumentAttachment;
use Headless\Accounting\Models\DocumentVersion;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * DocumentService — small utility wrapper around the polymorphic
 * DocumentAttachment table. Handles upload (filesystem), version bump,
 * OCR stub (just records "processed" + a placeholder payload), and
 * basic signature metadata.
 */
class DocumentService
{
    public function attach(Model $subject, UploadedFile $file, string $kind = 'evidence'): DocumentAttachment
    {
        $disk = Config::string('filesystems.default', 'local');
        $path = $file->store("headless-accounting/{$subject->getMorphClass()}/{$subject->getKey()}", $disk);

        return DocumentAttachment::create([
            'company_id' => $subject->company_id ?? null,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'extra_metadata' => ['kind' => $kind],
        ]);
    }

    /**
     * Bumps version: copies latest blob path to a new version row.
     */
    public function newVersion(DocumentAttachment $attachment, UploadedFile $file, $uploader, ?string $comment = null): DocumentVersion
    {
        $latest = $attachment->versions()->orderByDesc('version')->first();
        $next = ($latest->version ?? 0) + 1;
        $disk = $attachment->storage_disk;
        $path = $file->store("{$attachment->storage_path}/v{$next}", $disk);

        $v = DocumentVersion::create([
            'attachment_id' => $attachment->id,
            'version' => $next,
            'storage_path' => $path,
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
            'uploader_type' => $uploader?->getMorphClass(),
            'uploader_id' => $uploader?->getKey() ? (string) $uploader->getKey() : null,
            'comment' => $comment,
        ]);

        $attachment->storage_path = $path;
        $attachment->size_bytes = $v->size_bytes;
        $attachment->checksum_sha256 = $v->checksum_sha256;
        $attachment->save();

        return $v;
    }

    /**
     * Mock OCR — record `ocr_processed = true` and store a synthesized
     * result so the rest of the system can pick it up via the
     * DocumentAttachment::ocr_result cast.
     */
    public function runOcr(DocumentAttachment $attachment, ?callable $extractor = null): DocumentAttachment
    {
        $attachment->ocr_processed = true;
        $attachment->ocr_result = $extractor
            ? $extractor($attachment)
            : [
                'language' => Config::string('headless-accounting.locale.default'),
                'confidence' => 0.0,
                'note' => 'OCRPASS not performed (no extractor provided).',
            ];
        $attachment->save();

        return $attachment;
    }

    public function sign(DocumentAttachment $attachment, string $signer): DocumentAttachment
    {
        $attachment->requires_signature = true;
        $attachment->signed_at = now();
        $attachment->signed_by = $signer;
        $attachment->save();

        return $attachment;
    }
}
