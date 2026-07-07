<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\HR\Employee;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stocktake — periodic inventory count for one {@see Warehouse}.
 *
 * Lifecycle:
 *
 *   draft → counting → counted → under_review → approved → posted
 *                                                            ↓
 *                                                       cancelled
 *
 * Draft   — created, no counting started yet
 * Counting — counters assigned, lines being populated
 * Counted — every line has a count entered (possibly with shortages)
 * UnderReview — supervisor reviewing variances and adjusting
 * Approved — variances approved, ready to post to ledger
 * Posted  — inventory adjustments + journal entry written
 * Cancelled — abandoned without posting
 */
class Stocktake extends BaseModel
{
    use BelongsToCompany, HasFactory;

    public const SCOPE_FULL = 'full';

    public const SCOPE_CYCLE = 'cycle';

    public const SCOPE_ZONE = 'zone';

    public const SCOPE_VARIANT = 'variant';

    public const STATE_DRAFT = 'draft';

    public const STATE_COUNTING = 'counting';

    public const STATE_COUNTED = 'counted';

    public const STATE_UNDER_REVIEW = 'under_review';

    public const STATE_APPROVED = 'approved';

    public const STATE_POSTED = 'posted';

    public const STATE_CANCELLED = 'cancelled';

    protected string $tableSuffix = 'stocktakes';

    protected $fillable = [
        'company_id', 'warehouse_id', 'number',
        'state', 'scope',
        'scheduled_at', 'counted_at', 'approved_at', 'posted_at',
        'zones', 'variants', 'counters',
        'notes',
        'approved_by', 'posted_journal_entry_id',
    ];

    protected $casts = [
        'zones' => 'array',
        'variants' => 'array',
        'counters' => 'array',
        'scheduled_at' => 'date',
        'counted_at' => 'date',
        'approved_at' => 'date',
        'posted_at' => 'date',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class, 'stocktake_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function postedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'posted_journal_entry_id');
    }

    public function varianceSummary(): array
    {
        $lines = $this->lines()->get();
        $positiveValue = 0;
        $negativeValue = 0;
        $shortVariances = 0;
        $overVariances = 0;
        $uncounted = 0;

        foreach ($lines as $line) {
            if ($line->counted_quantity === null) {
                $uncounted++;

                continue;
            }
            $v = (int) $line->variance;
            $val = (int) $line->variance_value_minor;
            if ($v > 0) {
                $overVariances++;
                $positiveValue += $val;
            } elseif ($v < 0) {
                $shortVariances++;
                $negativeValue += abs($val);
            }
        }

        return [
            'lines_total' => $lines->count(),
            'uncounted' => $uncounted,
            'short_count' => $shortVariances,
            'over_count' => $overVariances,
            'short_value_minor' => $negativeValue,
            'over_value_minor' => $positiveValue,
            'net_value_minor' => $positiveValue - $negativeValue,
        ];
    }

    public function canTransitionTo(string $newState): bool
    {
        $allowed = [
            self::STATE_DRAFT => [self::STATE_COUNTING, self::STATE_CANCELLED],
            self::STATE_COUNTING => [self::STATE_COUNTED, self::STATE_UNDER_REVIEW, self::STATE_CANCELLED],
            self::STATE_COUNTED => [self::STATE_UNDER_REVIEW, self::STATE_CANCELLED],
            self::STATE_UNDER_REVIEW => [self::STATE_APPROVED, self::STATE_COUNTING, self::STATE_CANCELLED],
            self::STATE_APPROVED => [self::STATE_POSTED, self::STATE_CANCELLED],
            self::STATE_POSTED => [],
            self::STATE_CANCELLED => [],
        ];

        return in_array($newState, $allowed[$this->state] ?? [], true);
    }
}
