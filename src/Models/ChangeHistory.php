<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChangeHistory extends Model
{
    use BelongsToCompany;

    protected $table = 'int_change_history';

    protected $fillable = [
        'company_id', 'subject_type', 'subject_id',
        'actor_type', 'actor_id',
        'before', 'after', 'event', 'reason',
    ];

    protected $casts = ['before' => 'array', 'after' => 'array'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
