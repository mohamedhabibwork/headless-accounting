<?php

declare(strict_types=1);

namespace Headless\Accounting\FixedAssets;

use Carbon\CarbonImmutable;
use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\DepreciationLine;
use Headless\Accounting\Support\RoundingMode;

/**
 * DepreciationEngine — plugs three depreciation methods:
 *   - straight_line
 *   - declining_balance
 *   - units_of_production
 *
 * Each returns the periodic amount in minor units; the runner persists
 * DepreciationLine rows and posts the matching journal entry.
 */
class DepreciationEngine
{
    public const METHOD_STRAIGHT_LINE = 'straight_line';

    public const METHOD_DECLINING_BALANCE = 'declining_balance';

    public const METHOD_UNITS_OF_PROD = 'units_of_production';

    public function monthlyDepreciation(Asset $asset, ?CarbonImmutable $asOf = null): int
    {
        $asOf ??= CarbonImmutable::now();

        $monthly = match ($asset->depreciation_method) {
            self::METHOD_STRAIGHT_LINE => $this->straightLine($asset),
            self::METHOD_DECLINING_BALANCE => $this->decliningBalance($asset),
            self::METHOD_UNITS_OF_PROD => $this->unitsOfProduction($asset),
            default => 0,
        };

        // Cap so we never depreciate below the residual value.
        $maxDepreciable = (int) $asset->cost_minor - (int) $asset->residual_minor;
        $remaining = $maxDepreciable - (int) $asset->accumulated_depreciation_minor;

        return max(0, min($monthly, $remaining));
    }

    public function straightLine(Asset $asset): int
    {
        $life = max(1, (int) $asset->useful_life_years);

        return (int) RoundingMode::roundWith(((int) $asset->cost_minor - (int) $asset->residual_minor) / ($life * 12));
    }

    public function decliningBalance(Asset $asset): int
    {
        $rate = (float) $asset->depreciation_rate_pct ?: ((100 / max(1, (int) $asset->useful_life_years)) * 2);
        $bookValue = $asset->bookValueMinor();
        $annualAmount = (int) RoundingMode::roundWith($bookValue * ($rate / 100));

        return (int) RoundingMode::roundWith($annualAmount / 12);
    }

    public function unitsOfProduction(Asset $asset): int
    {
        // Caller must keep `metadata.units_per_year` on the asset for UoP to be meaningful.
        $unitsPerYear = (int) ($asset->description && preg_match('/UoP:(\d+)/', $asset->description, $m) ? $m[1] : 0);
        if (! $unitsPerYear || $asset->useful_life_years === 0) {
            return 0;
        }
        $totalUnits = $unitsPerYear * (int) $asset->useful_life_years;
        if (! $totalUnits) {
            return 0;
        }

        return (int) RoundingMode::roundWith(((int) $asset->cost_minor - (int) $asset->residual_minor) / $totalUnits);
    }

    /**
     * Persist one month's depreciation and post to the journal.
     */
    public function runForMonth(Asset $asset, string $period, CarbonImmutable $at, $journal): DepreciationLine
    {
        $amount = $this->monthlyDepreciation($asset, $at);
        if ($amount <= 0) {
            return DepreciationLine::firstOrNew(['asset_id' => $asset->id, 'period' => $period]);
        }

        $category = $asset->category;
        $debit = $category->depreciation_expense_account_id;
        $credit = $category->accumulated_depreciation_account_id;

        $entry = $journal->post(
            source: $asset,
            currency: $asset->currency,
            description: "Depreciation for {$asset->code} ({$period})",
            autoPosted: true,
            postings: [
                ['account_id' => $debit,  'debit' => $amount, 'memo' => 'Depreciation expense'],
                ['account_id' => $credit, 'credit' => $amount, 'memo' => 'Accumulated depreciation'],
            ],
        );

        $accumulated = (int) $asset->accumulated_depreciation_minor + $amount;
        $bookValue = (int) $asset->bookValueMinor() - $amount;

        return DepreciationLine::updateOrCreate(
            ['asset_id' => $asset->id, 'period' => $period],
            [
                'amount_minor' => $amount,
                'currency' => $asset->currency,
                'accumulated_minor' => $accumulated,
                'book_value_minor' => $bookValue,
                'fiscal_year' => (int) CarbonImmutable::parse($period)->year,
                'journal_entry_id' => $entry->id,
                'posted' => true,
            ],
        );
    }
}
