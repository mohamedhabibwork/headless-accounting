<?php

declare(strict_types=1);

namespace Headless\Accounting\Automation;

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Accounting\RecurringJournal;
use Headless\Accounting\Accounting\RecurringJournalRun;

/**
 * RecurringJournalRunner — walks every active recurring journal whose
 * `next_run_at` is on or before today and posts the corresponding
 * JournalEntry. Designed to be invoked from a scheduled console
 * command (`php artisan ha:run-recurring`) or a Laravel scheduler hook.
 */
class RecurringJournalRunner
{
    public function __construct(private readonly Journal $journal) {}

    /**
     * @return array<int, RecurringJournalRun>
     */
    public function run(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $hits = [];

        $rules = RecurringJournal::query()
            ->where('active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('next_run_at')->orWhereDate('next_run_at', '<=', $today);
            })
            ->get();

        foreach ($rules as $rule) {
            $hits[] = $this->execute($rule, $today);
        }

        return $hits;
    }

    public function execute(RecurringJournal $rule, ?CarbonImmutable $today = null): RecurringJournalRun
    {
        $today ??= CarbonImmutable::today();

        $run = RecurringJournalRun::create([
            'recurring_journal_id' => $rule->id,
            'run_at' => now(),
            'status' => 'pending',
        ]);

        if ($rule->isExhausted()) {
            $rule->active = false;
            $rule->save();
            $run->update(['status' => 'skipped']);

            return $run;
        }

        // Resolve the source that the entry's postings will be tied to.
        $source = $rule;       // posting against the rule itself is the simplest
        $rows = $rule->template_id
            ? $rule->template->materializeRows()
            : (array) $rule->lines;

        try {
            $entry = $this->journal->post(
                source: $source,
                currency: $rule->currency,
                description: 'Recurring journal: '.$rule->name,
                autoPosted: false,
                postings: $rows,
            );

            $rule->update([
                'last_run_at' => $today->toDateString(),
                'next_run_at' => $rule->advance($today)?->toDateString(),
                'occurrences_count' => $rule->occurrences_count + 1,
            ]);

            $run->update(['status' => 'posted', 'journal_entry_id' => $entry->id]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $run;
    }
}
