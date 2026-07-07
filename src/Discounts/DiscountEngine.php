<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts;

use Headless\Accounting\Discounts\Contracts\Condition;
use Headless\Accounting\Discounts\Contracts\Limitation;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\OrderItem;
use InvalidArgumentException;

/**
 * DiscountEngine — orchestrates conditions, drivers, limitations,
 * stacking and priority for a given EvaluationContext.
 *
 *   1. Fetch active discounts (DB query happens once, outside the engine).
 *   2. Sort by `priority` ascending.
 *   3. For each discount:
 *        a. evaluate every Condition — skip if any fails
 *        b. resolve candidate lines from the context
 *        c. ask the driver to compute DiscountApplication
 *        d. apply each Limitation (in order)
 *        e. apply the result to the rolling subtotal if not stackable
 *
 *   Returns an array of {@see DiscountApplication} in evaluation order.
 */
final class DiscountEngine
{
    /**
     * @param  iterable<Discount>  $candidates
     * @param  iterable<Condition>  $conditionPool  Built from the configured pool.
     * @param  iterable<Limitation>  $limitationPool
     */
    public function __construct(
        private readonly ConditionFactory $conditionFactory,
        private readonly LimitationFactory $limitationFactory,
        private readonly array $config,
    ) {}

    /**
     * @param  iterable<Discount>  $candidates
     * @return DiscountApplication[]
     */
    public function run(iterable $candidates, EvaluationContext $ctx): array
    {
        $apps = [];
        $runningSubtotal = 0;
        foreach ($ctx->items as $i) {
            if ($i instanceof OrderItem) {
                $runningSubtotal += $i->unit_price_minor * $i->quantity;
            }
        }

        $sorted = collect($candidates)->sortBy('priority')->values();

        foreach ($sorted as $discount) {
            /** @var Discount $discount */
            if (! $discount->isCurrentlyActive()) {
                continue;
            }

            $cfg = array_merge((array) $discount->config, ['__name' => $discount->name]);
            $discount->driver()->setConfig($cfg);

            if (! $this->evaluateConditions($discount, $ctx)) {
                continue;
            }

            $candidateLines = $this->resolveCandidateLines($discount, $ctx);
            if ($candidateLines === []) {
                continue;
            }

            try {
                $app = $discount->driver()->calculate($ctx, $candidateLines);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            $app->discountId = $discount->id;
            $app = $this->applyLimitations($discount, $ctx, $app);

            if ($app->isEmpty()) {
                continue;
            }

            $apps[] = $app;

            if (! $discount->stackable || ! $this->config['stackable']) {
                // Subtract from running subtotal to drive future passes.
                $runningSubtotal = max(0, $runningSubtotal - $app->total->amount);
            }
        }

        return $apps;
    }

    private function evaluateConditions(Discount $discount, EvaluationContext $ctx): bool
    {
        foreach ($discount->conditions as $cond) {
            $instance = $this->conditionFactory->make((string) $cond->type);
            if (! $instance instanceof Condition) {
                throw new InvalidArgumentException("Unknown condition type: {$cond->type}");
            }
            $instance->setConfig((array) $cond->config);
            if (! $instance->passes($ctx)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return OrderItem[]
     */
    private function resolveCandidateLines(Discount $discount, EvaluationContext $ctx): array
    {
        // If the discount has explicit discountable targets, restrict to them.
        $targetIds = $discount->targets->pluck('target_id')->all();
        $lines = [];
        foreach ($ctx->items as $i) {
            if (! $i instanceof OrderItem) {
                continue;
            }
            if ($targetIds === [] || in_array((int) $i->getKey(), $targetIds, true)) {
                $lines[] = $i;
            }
        }

        return $lines;
    }

    private function applyLimitations(Discount $discount, EvaluationContext $ctx, DiscountApplication $app): DiscountApplication
    {
        foreach ($discount->limitations as $lim) {
            $inst = $this->limitationFactory->make((string) $lim->type);
            if (! $inst instanceof Limitation) {
                throw new InvalidArgumentException("Unknown limitation type: {$lim->type}");
            }
            $inst->setConfig((array) $lim->config);
            $app = $inst->apply($ctx, $app);
            if ($app->isEmpty()) {
                break;
            }
        }

        return $app;
    }
}
