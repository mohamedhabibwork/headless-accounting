<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WriteOff extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'write_offs';

    protected $fillable = [
        'company_id', 'source_type', 'source_id',
        'currency', 'amount_minor', 'reason',
    ];

    protected $casts = ['amount_minor' => 'integer'];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
