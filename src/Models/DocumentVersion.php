<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentVersion extends Model
{
    protected $table = 'doc_versions';

    protected $fillable = [
        'attachment_id', 'version', 'storage_path',
        'checksum_sha256', 'size_bytes',
        'uploader_type', 'uploader_id', 'comment',
    ];

    protected $casts = ['size_bytes' => 'integer', 'version' => 'integer'];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(DocumentAttachment::class, 'attachment_id');
    }

    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }
}
