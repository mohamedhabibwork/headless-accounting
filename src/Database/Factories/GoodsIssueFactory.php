<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\GoodsIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoodsIssueFactory extends Factory
{
    protected $model = GoodsIssue::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'warehouse_id' => null,
            'number' => 'GI-'.date('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'reason' => GoodsIssue::REASON_CONSUMPTION,
            'issued_at' => now()->toDateString(),
            'state' => GoodsIssue::STATE_DRAFT,
            'cost_center_id' => null,
            'project_id' => null,
            'lines' => [],
            'notes' => null,
            'journal_entry_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['state' => GoodsIssue::STATE_APPROVED]);
    }

    public function posted(): static
    {
        return $this->state(['state' => GoodsIssue::STATE_POSTED]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => GoodsIssue::STATE_CANCELLED]);
    }

    public function forSales(): static
    {
        return $this->state(['reason' => GoodsIssue::REASON_SALES]);
    }

    public function forSampling(): static
    {
        return $this->state(['reason' => GoodsIssue::REASON_SAMPLING]);
    }

    public function forDamage(): static
    {
        return $this->state(['reason' => GoodsIssue::REASON_DAMAGE]);
    }

    public function forLoss(): static
    {
        return $this->state(['reason' => GoodsIssue::REASON_LOSS]);
    }

    public function forProduction(): static
    {
        return $this->state(['reason' => GoodsIssue::REASON_PRODUCTION]);
    }
}
