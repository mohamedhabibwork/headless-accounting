<?php

declare(strict_types=1);

namespace Headless\Accounting\Reporting;

use Headless\Accounting\Models\Project;
use Headless\Accounting\Models\ProjectTimeBill;
use Headless\Accounting\Models\ProjectWip;

/**
 * ProjectProfitabilityReport — aggregates costs, time-billed amount
 * and invoiced revenue per project.
 */
class ProjectProfitabilityReport
{
    public function forProject(int $projectId): array
    {
        $project = Project::findOrFail($projectId);
        $wip = ProjectWip::query()->where('project_id', $project->id)->orderByDesc('as_of')->first();

        $timeBillAmount = (int) ProjectTimeBill::query()
            ->where('project_id', $projectId)
            ->sum('amount_minor');

        $milestoneRevenue = (int) $project->milestones()->sum('revenue_minor');

        return [
            'project' => $project->only(['id', 'name', 'budget_minor', 'currency']),
            'time_billed_amount_minor' => $timeBillAmount,
            'milestone_revenue_minor' => $milestoneRevenue,
            'total_revenue_minor' => $timeBillAmount + $milestoneRevenue,
            'progress_pct' => (float) $project->progress_pct,
            'wip' => $wip ? [
                'as_of' => $wip->as_of?->toDateString(),
                'costs_minor' => (int) $wip->costs_minor,
                'recognized_revenue_minor' => (int) $wip->recognized_revenue_minor,
                'over_under_minor' => (int) $wip->over_under_minor,
            ] : null,
        ];
    }

    public function ranked(int $companyId, string $orderBy = 'total_revenue_minor'): array
    {
        $projects = Project::query()->where('company_id', $companyId)->get();
        $data = $projects->map(function (Project $p) {
            $row = $this->forProject($p->id);

            return $row + ['project_id' => $p->id, 'project_name' => $p->name];
        })->all();

        usort($data, fn ($a, $b) => ($b[$orderBy] ?? 0) <=> ($a[$orderBy] ?? 0));

        return $data;
    }
}
