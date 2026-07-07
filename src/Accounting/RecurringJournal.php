<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringJournal extends BaseModel
{
    use BelongsToCompany;

    public const FREQ_DAILY = 'daily';

    public const FREQ_WEEKLY = 'weekly';

    public const FREQ_BIWEEKLY = 'biweekly';

    public const FREQ_MONTHLY = 'monthly';

    public const FREQ_QUARTERLY = 'quarterly';

    public const FREQ_YEARLY = 'yearly';

    protected string $tableSuffix = 'recurring_journals';

    protected $fillable = [
        'company_id', 'template_id', 'name', 'description',
        'currency', 'frequency',
        'day_of_month', 'day_of_week',
        'start_date', 'end_date', 'next_run_at', 'last_run_at',
        'max_occurrences', 'occurrences_count',
        'lines', 'active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_at' => 'date',
        'last_run_at' => 'date',
        'lines' => 'array',
        'active' => 'boolean',
        'occurrences_count' => 'integer',
        'max_occurrences' => 'integer',
        'day_of_month' => 'integer',
        'day_of_week' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(JournalTemplate::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(RecurringJournalRun::class);
    }

    /** Computes the next firing time given `last_run_at` (or start_date). */
    public function advance(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        $from = $from ? $from->toDate() : ($this->last_run_at ?: $this->start_date);

        return match ($this->frequency) {
            self::FREQ_DAILY => CarbonImmutable::parse($from)->addDay(),
            self::FREQ_WEEKLY => CarbonImmutable::parse($from)->addWeek(),
            self::FREQ_BIWEEKLY => CarbonImmutable::parse($from)->addWeeks(2),
            self::FREQ_MONTHLY => CarbonImmutable::parse($from)->addMonth(),
            self::FREQ_QUARTERLY => CarbonImmutable::parse($from)->addQuarter(),
            self::FREQ_YEARLY => CarbonImmutable::parse($from)->addYear(),
            default => null,
        };
    }

    public function isExhausted(): bool
    {
        if ($this->max_occurrences && $this->occurrences_count >= $this->max_occurrences) {
            return true;
        }

        return $this->end_date && CarbonImmutable::now()->gt($this->end_date);
    }
}
