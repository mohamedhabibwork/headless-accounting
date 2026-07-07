<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentAttachment extends Model
{
    use BelongsToCompany;

    protected $table = 'doc_attachments';

    protected $fillable = [
        'company_id', 'subject_type', 'subject_id',
        'filename', 'mime_type', 'size_bytes',
        'storage_disk', 'storage_path', 'checksum_sha256',
        'ocr_processed', 'ocr_result',
        'extra_metadata',
        'requires_signature', 'signed_at', 'signed_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'ocr_processed' => 'boolean',
        'ocr_result' => 'array',
        'extra_metadata' => 'array',
        'requires_signature' => 'boolean',
        'signed_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'attachment_id')->orderByDesc('version');
    }
}
