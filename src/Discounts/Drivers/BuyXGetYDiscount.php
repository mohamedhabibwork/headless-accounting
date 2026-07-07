<?php

declare(strict_types=1);

namespace Headless\Accounting\Discounts\Drivers;

use Headless\Accounting\Currency\Money;
use Headless\Accounting\Discounts\BaseDiscountDriver;
use Headless\Accounting\Discounts\DiscountApplication;
use Headless\Accounting\Discounts\EvaluationContext;
use Headless\Accounting\Models\OrderItem;
use Headless\Accounting\Support\RoundingMode;

/**
 * BuyXGetYDiscount — "Buy X get Y". Classic BOGO and its kin.
 *
 * Configuration:
 *   - buy_qty                    (int)   — e.g. 2
 *   - get_qty                    (int)   — e.g. 1
 *   - get_discount_percent       (float) — 100 = free, 50 = half off
 *   - buy_products               (int[]/string[])  — products that count toward "buy"
 *   - get_products               (int[]/string[])  — products eligible for "get"
 *   - same_product_only          (bool)  — restrict to identical variant
 *   - selection                  ('cheapest'|'most_expensive'|'specific', default cheapest)
 *   - max_applications           (int|null) — optional local hard cap
 *
 * The driver counts qualifying `buy` units, then for every full
 * buy_qty+get_qty cycle it applies `get_discount_percent` to the
 * cheapest/most_expensive set of `get` units.
 */
final class BuyXGetYDiscount extends BaseDiscountDriver
{
    public function type(): string
    {
        return 'buy_x_get_y';
    }

    public function calculate(EvaluationContext $ctx, iterable $candidateLines): DiscountApplication
    {
        $buyQty = max(1, (int) $this->config('buy_qty', 2));
        $getQty = max(1, (int) $this->config('get_qty', 1));
        $getPercent = (float) $this->config('get_discount_percent', 100);

        $buyProducts = (array) $this->config('buy_products', []);
        $getProducts = (array) $this->config('get_products', []);
        $sameOnly = (bool) $this->config('same_product_only', false);
        $selection = (string) $this->config('selection', 'cheapest');
        $maxApps = $this->config('max_applications');

        $rounding = $this->roundingMode();
        $currency = $this->currency($ctx);

        $lines = [];
        foreach ($candidateLines as $line) {
            if ($line instanceof OrderItem) {
                $lines[] = $line;
            }
        }
        if ($lines === []) {
            return new DiscountApplication(0, 'bxgy', Money::zero($currency), Money::zero($currency));
        }

        // Bucket by product_id, get total qualifying quantities.
        $buyUnits = 0;
        $buyLines = [];
        $getLines = [];
        foreach ($lines as $line) {
            $productId = $this->resolveProductId($line);

            if ($buyProducts === [] || in_array($productId, $buyProducts, true)) {
                $buyUnits += (int) $line->quantity;
                $buyLines[] = $line;
            }
            if ($getProducts === [] || in_array($productId, $getProducts, true)) {
                $getLines[] = $line;
            }
        }
        if ($sameOnly && $getProducts === []) {
            $getLines = $buyLines;
        }

        $cycles = intdiv($buyUnits, $buyQty);
        if ($maxApps !== null) {
            $cycles = min($cycles, (int) $maxApps);
        }
        if ($cycles === 0) {
            return new DiscountApplication(0, 'bxgy', Money::zero($currency), Money::zero($currency));
        }

        // Flatten get_units as individual units we can pick from.
        $units = [];
        foreach ($getLines as $line) {
            for ($i = 0; $i < (int) $line->quantity; $i++) {
                $units[] = [
                    'variant_id' => $line->variant_id,
                    'product_id' => $this->resolveProductId($line),
                    'unit_price' => (int) $line->unit_price_minor,
                    'currency' => $line->currency ?? $currency,
                ];
            }
        }

        // Pick the units we discount.
        usort($units, fn ($a, $b) => match ($selection) {
            'most_expensive' => $b['unit_price'] <=> $a['unit_price'],
            default => $a['unit_price'] <=> $b['unit_price'],
        });

        $take = $cycles * $getQty;
        $picked = array_slice($units, 0, $take);
        $totalDiscount = 0;
        $totalRequested = 0;

        foreach ($picked as $u) {
            $price = $u['unit_price'];
            $req = (int) RoundingMode::roundWith($price);
            $reqDiscount = (int) RoundingMode::roundWith($price * ($getPercent / 100));
            $totalRequested += $price;
            $totalDiscount += $reqDiscount;
        }

        // Money objects for the application
        $appTotal = new Money($totalDiscount, $currency);
        $appRequest = new Money((int) RoundingMode::roundWith($totalRequested * ($getPercent / 100)), $currency);

        $app = new DiscountApplication(
            discountId: $ctx->order?->id ?? 0,
            discountName: $this->config('__name', 'bxgy'),
            total: $appTotal,
            requested: $appRequest,
        );

        // Group per-variant application so the engine can write per-line
        // adjustments.
        $grouped = [];
        foreach ($picked as $u) {
            $key = $u['variant_id'];
            $grouped[$key] = ($grouped[$key] ?? 0) + (int) RoundingMode::roundWith($u['unit_price'] * ($getPercent / 100));
        }
        foreach ($grouped as $variantId => $amount) {
            $app->addLine(
                new Money($amount, $currency),
                new Money((int) RoundingMode::roundWith($amount / ($getPercent / 100)), $currency),
                ['variant_id' => $variantId],
            );
        }

        return $app;
    }

    private function resolveProductId(OrderItem $line): int
    {
        return (int) ($line->variant->product_id ?? 0);
    }
}
