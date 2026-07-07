<?php

declare(strict_types=1);

namespace Headless\Accounting\FixedAssets;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Exceptions\InvalidTransitionException;
use Headless\Accounting\Models\Asset;
use Headless\Accounting\Models\AssetDisposal;

/**
 * DisposeAsset — closes an asset, posts gain/loss, and stamps state.
 */
class DisposeAsset
{
    public function __construct(private readonly Journal $journal) {}

    public function execute(
        Asset $asset,
        string $method = 'sold',
        int $proceedsMinor = 0,
        ?string $reason = null,
    ): AssetDisposal {
        if ($asset->state !== 'active') {
            throw new InvalidTransitionException("Asset {$asset->code} is already {$asset->state}.");
        }

        $bookValue = $asset->bookValueMinor();
        $gainLoss = $proceedsMinor - $bookValue;

        // Post disposal:
        //   Dr Cash (proceeds)                    [10000]
        //   Dr Accumulated Depreciation           [accumulated]
        //   Cr Asset                               [cost]
        //   Cr/Dr Gain/Loss on Disposal           [delta]
        $postings = [
            ['account_id' => $asset->chart_account_id, 'credit' => (int) $asset->cost_minor, 'memo' => 'Asset cost removed'],
            ['account_id' => $asset->category->accumulated_depreciation_account_id, 'debit' => (int) $asset->accumulated_depreciation_minor, 'memo' => 'Accumulated depreciation cleared'],
        ];
        if ($proceedsMinor > 0) {
            array_unshift($postings, ['account_id' => '1100', 'debit' => $proceedsMinor, 'memo' => 'Cash received']);
        }
        if ($gainLoss > 0) {
            $postings[] = ['account_id' => '7000', 'credit' => abs($gainLoss), 'memo' => 'Gain on disposal'];
        } elseif ($gainLoss < 0) {
            $postings[] = ['account_id' => '7000', 'debit' => abs($gainLoss), 'memo' => 'Loss on disposal'];
        }

        $entry = $this->journal->post(
            source: $asset, currency: $asset->currency,
            description: "Asset disposal ({$method}): {$asset->code}",
            autoPosted: true,
            postings: $postings,
        );

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'disposed_at' => now()->toDateString(),
            'method' => $method,
            'proceeds_minor' => $proceedsMinor,
            'cost_at_disposal_minor' => (int) $asset->cost_minor,
            'accumulated_at_disposal_minor' => (int) $asset->accumulated_depreciation_minor,
            'gain_loss_minor' => $gainLoss,
            'journal_entry_id' => $entry->id,
            'notes' => $reason,
        ]);

        $asset->state = 'disposed';
        $asset->disposed_at = now()->toDateString();
        $asset->save();

        return $disposal;
    }
}
