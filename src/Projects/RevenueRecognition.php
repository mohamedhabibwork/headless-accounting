<?php

declare(strict_types=1);

namespace Headless\Accounting\Projects;

use Carbon\CarbonImmutable;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\ProjectMilestone;
use Headless\Accounting\Models\ProjectWip;
use Headless\Accounting\Support\Config;

/**
 * RevenueRecognition — implements two project-accounting policies:
 *   - percent_of_completion: WIP = cost * (recognized/cost). Used for
 *     long-running fixed-price engagements.
 *   - milestone: revenue is recognized only when a milestone is
 *     marked "achieved".
 */
class RevenueRecognition
{
    public function __construct(private readonly Journal $journal) {}

    /**
     * @return array<string, mixed> Updated WIP summary.
     */
    public function percentOfCompletion(Project $project, int $costsIncurredMinor, int $recognizedMinor, CarbonImmutable $asOf, string $wipAccount = '1400'): ProjectWip
    {
        $billedSoFar = $recognizedMinor;
        $costIncurred = $costsIncurredMinor;
        $grossProfit = $billedSoFar - $costIncurred;
        $project->progress_pct = $project->budget_minor > 0
            ? min(100.0, ($billedSoFar / $project->budget_minor) * 100.0)
            : 0;
        $project->save();

        // WIP journal: Dr WIP, Cr Revenue-to-Recognize (until completed; flip later).
        if ($grossProfit !== 0) {
            $this->journal->post(
                source: $project,
                currency: $project->currency,
                description: 'WIP recognition for project '.$project->code,
                autoPosted: true,
                postings: [
                    ['account' => $wipAccount, 'debit' => abs($grossProfit), 'memo' => 'WIP'],
                    ['account' => Config::string('headless-accounting.accounting.accounts.sales_revenue'),       'credit' => abs($grossProfit), 'memo' => 'Recognized revenue'],
                ],
            );
        }

        return ProjectWip::updateOrCreate(
            ['project_id' => $project->id, 'as_of' => $asOf->toDateString(), 'currency' => $project->currency],
            [
                'company_id' => $project->company_id,
                'costs_minor' => $costIncurred,
                'recognized_revenue_minor' => $billedSoFar,
                'over_under_minor' => $billedSoFar - $costIncurred,
            ],
        );
    }

    public function recognizeMilestone(ProjectMilestone $milestone): bool
    {
        if ($milestone->invoiced || ! $milestone->achieved_at) {
            return false;
        }

        if ($milestone->invoice_id) {
            // milestone was tied to an invoice; flip the flag.
            $milestone->invoiced = true;
            $milestone->save();
        }

        $this->journal->post(
            source: $milestone,
            currency: $milestone->currency,
            description: 'Milestone recognized: '.$milestone->name,
            autoPosted: true,
            postings: [
                ['account' => '2300', 'debit' => (int) $milestone->revenue_minor, 'memo' => 'Customer prepayment cleared'],
                ['account' => Config::string('headless-accounting.accounting.accounts.sales_revenue'), 'credit' => (int) $milestone->revenue_minor, 'memo' => 'Revenue recognized'],
            ],
        );

        return true;
    }
}
