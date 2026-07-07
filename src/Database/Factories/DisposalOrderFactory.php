<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\DisposalOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisposalOrderFactory extends Factory
{
    protected $model = DisposalOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'number' => 'DSP-'.date('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'method' => DisposalOrder::METHOD_SCRAP,
            'disposed_at' => null,
            'state' => DisposalOrder::STATE_DRAFT,
            'reason' => null,
            'notes' => null,
            'journal_entry_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['state' => DisposalOrder::STATE_APPROVED]);
    }

    public function executed(): static
    {
        return $this->state([
            'state' => DisposalOrder::STATE_EXECUTED,
            'disposed_at' => now()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => DisposalOrder::STATE_CANCELLED]);
    }

    public function method(string $method): static
    {
        return $this->state(['method' => $method]);
    }

    public function methodRecycle(): static
    {
        return $this->state(['method' => DisposalOrder::METHOD_RECYCLE]);
    }

    public function returnToVendor(): static
    {
        return $this->state(['method' => DisposalOrder::METHOD_RETURN_TO_VENDOR]);
    }

    public function methodSell(): static
    {
        return $this->state(['method' => DisposalOrder::METHOD_SELL]);
    }
}
