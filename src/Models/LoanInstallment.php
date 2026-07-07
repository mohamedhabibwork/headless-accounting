<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $table = 'hr_loan_installments';

    protected $fillable = [
        'loan_id', 'installment_no', 'due_date', 'currency',
        'principal_minor', 'interest_minor', 'total_minor',
        'paid_minor', 'paid_at', 'state',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'date',
        'principal_minor' => 'integer',
        'interest_minor' => 'integer',
        'total_minor' => 'integer',
        'paid_minor' => 'integer',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
