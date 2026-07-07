<?php

declare(strict_types=1);

namespace Headless\Accounting\Tax;

use Headless\Accounting\Contracts\Taxable;
use Headless\Accounting\Currency\Money;
use Headless\Accounting\Models\Address;
use Headless\Accounting\Models\TaxRate;
use Headless\Accounting\Models\TaxZone;
use Headless\Accounting\Support\RoundingMode;

/**
 * TaxEngine — resolves a TaxBreakdown for a subject.
 *
 *   - resolve the applicable TaxZone from `shipTo` (or `billTo`).
 *   - filter rates by class and zone.
 *   - apply rates in priority order. Compound rates layer on top of others.
 *   - emit a stable TaxBreakdown for invoice rendering.
 */
final class TaxEngine
{
    public function __construct(private readonly array $config) {}

    public function resolve(Taxable $subject, int $subtotalMinor, string $currency, ?Address $shipTo = null, ?Address $billTo = null, array $context = []): TaxBreakdown
    {
        $zone = $this->resolveZone($shipTo, $billTo);
        $inclusive = (bool) ($zone?->active && $this->cfg('inclusive', false));

        $breakdown = new TaxBreakdown($currency, new Money($subtotalMinor, $currency), $inclusive);

        if (! $zone) {
            return $breakdown;
        }

        $rates = $this->applicableRates($zone, $subject);

        // Sort by priority asc. Compound rates follow non-compound.
        usort($rates, function (TaxRate $a, TaxRate $b) {
            if ($a->compound !== $b->compound) {
                return $a->compound ? 1 : -1;
            }

            return $a->priority <=> $b->priority;
        });

        $alreadyTaxed = 0;
        $roundMode = (string) $this->cfg('round', RoundingMode::HalfEven->value);
        foreach ($rates as $rate) {
            $taxMinor = $rate->calculateTax($subtotalMinor, $alreadyTaxed, $roundMode);
            if ($taxMinor === 0) {
                continue;
            }

            $breakdown->add(new TaxLine(
                rateId: $rate->id,
                rateName: $rate->name,
                percent: $rate->percent,
                amount: new Money($taxMinor, $currency),
                compound: $rate->compound,
            ));
            $alreadyTaxed += $taxMinor;
        }

        return $breakdown;
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function resolveZone(?Address $shipTo, ?Address $billTo): ?TaxZone
    {
        $strategy = (string) $this->cfg('resolver_strategy', 'highest_rate_wins');

        $addr = $shipTo ?? $billTo;
        if (! $addr) {
            // No address: fall back to the configured default zone (if any).
            if ($strategy === 'default_zone') {
                $default = (string) $this->cfg('default_zone', '');
                if ($default !== '') {
                    $zone = TaxZone::query()->with('members')->where('code', $default)->first();
                    if ($zone) {
                        return $zone;
                    }
                }
            }

            return null;
        }

        $country = $addr->country_code;
        $region = $addr->region;
        $postal = $addr->postal_code;

        $zones = TaxZone::query()->with('members')->where('active', true)->get();

        // 'compound' strategy stacks zones; everything else picks the first match.
        if ($strategy === 'compound') {
            // Sort by priority asc so the strongest zone wins on conflicts.
            return $zones
                ->filter(fn ($z) => $this->zoneMatches($z, $country, $region, $postal))
                ->sortBy('priority')
                ->first();
        }

        // Default: 'highest_rate_wins' (first match wins, zones ordered by priority asc).
        foreach ($zones as $zone) {
            /** @var TaxZone $zone */
            if ($this->zoneMatches($zone, $country, $region, $postal)) {
                return $zone;
            }
        }

        return null;
    }

    private function zoneMatches(TaxZone $zone, ?string $country, ?string $region, ?string $postal): bool
    {
        if ($zone->members->isNotEmpty()) {
            $or = true;
            $matched = false;
            foreach ($zone->members as $m) {
                if ($m->matches($country, $region, $postal)) {
                    if ($m->operator === 'or') {
                        $matched = true;
                        break;
                    }
                    $matched = true;
                } elseif ($m->operator === 'and') {
                    $or = false;
                }
            }

            return $matched && $or;
        }

        return $country && str_starts_with($zone->code, strtolower($country));
    }

    /** @return TaxRate[] */
    private function applicableRates(TaxZone $zone, Taxable $subject): array
    {
        $classId = $subject->taxClassId();

        return $zone->rates()
            ->where('active', true)
            ->when($classId !== null, fn ($q) => $q->where('tax_class_id', $classId))
            ->orderBy('priority')
            ->get()
            ->all();
    }
}
