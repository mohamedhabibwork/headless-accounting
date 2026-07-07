<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Stocktake;

use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\StocktakePosted;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\InventoryAdjustment;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\StocktakeLine;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Support\Config;
use Illuminate\Support\Facades\DB;

/**
 * PostStocktake — applies approved variances:
 *   1. recalculates `variance_value_minor` from the variant's current
 *      cost layer (FIFO/LIFO/WA/standard) on each line;
 *   2. flips the stocktake state to `posted`;
 *   3. writes a single InventoryAdjustment document;
 *   4. adjusts {@see StockItem} balances (on_hand) and writes
 *      compensating `StockMovement` rows;
 *   5. opens a balanced journal entry
 *      Dr/Cr Inventory Asset  vs. Cr/Dr Inventory Variance (P&L);
 *
 * Re-posts are blocked (idempotency: posted state is terminal).
 */
final class PostStocktake extends Action
{
    public function __construct(private readonly Journal $journal) {}

    protected function handle(Stocktake $stocktake, ?string $currency = null): Stocktake
    {
        if ($stocktake->state === Stocktake::STATE_POSTED) {
            throw new AccountingException("Stocktake {$stocktake->number} is already posted.");
        }
        if (! in_array($stocktake->state, [Stocktake::STATE_APPROVED, Stocktake::STATE_UNDER_REVIEW], true)) {
            throw new AccountingException(
                "Stocktake {$stocktake->number} must be approved before posting (state={$stocktake->state})."
            );
        }

        $warehouse = $stocktake->warehouse;
        if (! $warehouse) {
            throw new AccountingException('Stocktake has no warehouse.');
        }
        $currency ??= Config::string('headless-accounting.accounting.default_currency');

        $existingCompanyId = (int) ($stocktake->company_id ?? 0);
        $warehouseOwnerCompanyId = (int) ($warehouse->owner_company_id ?? 0);
        $candidateCompanyId = $existingCompanyId ?: $warehouseOwnerCompanyId;

        if ($candidateCompanyId > 0 && $candidateCompanyId !== $existingCompanyId) {
            $stocktake->company_id = $candidateCompanyId;
            $stocktake->save();
        }
        $companyId = $candidateCompanyId > 0 ? $candidateCompanyId : null;

        $lines = $stocktake->lines()->whereNotNull('counted_quantity')->get();
        if ($lines->isEmpty()) {
            throw new AccountingException('No counted lines to post.');
        }

        $adjustmentLines = [];
        $totalShortMinor = 0;
        $totalOverMinor = 0;

        DB::transaction(function () use ($lines, $stocktake, $warehouse, $currency, $companyId, &$adjustmentLines, &$totalShortMinor, &$totalOverMinor) {
            foreach ($lines as $line) {
                $unitCost = (int) ($line->unit_cost_minor ?? $this->resolveUnitCost($line, $warehouse));
                $line->unit_cost_minor = $unitCost;
                $line->variance_value_minor = $unitCost * (int) $line->variance;
                $line->currency = $currency;
                $line->state = StocktakeLine::STATE_APPROVED;
                $line->save();

                if ((int) $line->variance === 0) {
                    continue;
                }

                if ((int) $line->variance < 0) {
                    $totalShortMinor += abs((int) $line->variance_value_minor);
                } else {
                    $totalOverMinor += abs((int) $line->variance_value_minor);
                }

                $stockItem = StockItem::query()
                    ->where('variant_id', $line->variant_id)
                    ->where('location_id', $warehouse->location_id)
                    ->first();
                if ($stockItem) {
                    $stockItem->on_hand = max(0, (int) $stockItem->on_hand + (int) $line->variance);
                    $stockItem->save();

                    StockMovement::create([
                        'stock_item_id' => $stockItem->id,
                        'reason' => 'stocktake',
                        'quantity' => (int) $line->variance,
                        'balance_after' => $stockItem->on_hand,
                        'source_type' => $stocktake->getMorphClass(),
                        'source_id' => $stocktake->id,
                        'occurred_at' => now(),
                    ]);
                }

                $adjustmentLines[] = [
                    'variant_id' => $line->variant_id,
                    'sku' => $line->variant?->sku,
                    'system_quantity' => (int) $line->system_quantity,
                    'counted_quantity' => (int) $line->counted_quantity,
                    'variance' => (int) $line->variance,
                    'unit_cost_minor' => $unitCost,
                    'variance_value_minor' => (int) $line->variance_value_minor,
                ];
            }

            $adjustment = InventoryAdjustment::create([
                'number' => $this->nextAdjustmentNumber(),
                'company_id' => $companyId,
                'location_id' => $warehouse->location_id,
                'adjusted_at' => now()->toDateString(),
                'reason' => 'stocktake:'.$stocktake->number,
                'lines' => $adjustmentLines,
                'notes' => 'Posted from stocktake '.$stocktake->number,
            ]);

            $entry = null;
            if ($totalShortMinor !== 0 || $totalOverMinor !== 0) {
                $net = $totalOverMinor - $totalShortMinor;
                $postings = [];
                if ($totalShortMinor > 0) {
                    $postings[] = ['account' => '5000', 'debit' => $totalShortMinor, 'memo' => 'Inventory variance (shrinkage)'];
                }
                if ($totalOverMinor > 0) {
                    $postings[] = ['account' => '5000', 'credit' => $totalOverMinor, 'memo' => 'Inventory variance (overage)'];
                }
                $postings[] = ['account' => '1400', 'debit' => $net >= 0 ? $net : 0, 'credit' => $net < 0 ? abs($net) : 0, 'memo' => 'Inventory Asset'];

                $entry = $this->journal->post(
                    source: $stocktake,
                    currency: $currency,
                    description: 'Stocktake variance '.$stocktake->number,
                    autoPosted: true,
                    postings: $postings,
                );
                $adjustment->update(['journal_entry_id' => $entry->id]);
            }

            $stocktake->state = Stocktake::STATE_POSTED;
            $stocktake->posted_at = now()->toDateString();
            $stocktake->posted_journal_entry_id = $entry?->id;
            $stocktake->save();
        });

        $fresh = $stocktake->fresh('lines');

        StocktakePosted::dispatch($fresh, $fresh->posted_journal_entry_id
            ? JournalEntry::find($fresh->posted_journal_entry_id)
            : null);

        return $fresh;
    }

    protected function resolveUnitCost(StocktakeLine $line, Warehouse $warehouse): int
    {
        $layer = CostLayer::query()
            ->where('variant_id', $line->variant_id)
            ->where('location_id', $warehouse->location_id)
            ->where('quantity_remaining', '>', 0)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();

        return (int) ($layer?->unit_cost_minor ?? 0);
    }

    protected function nextAdjustmentNumber(): string
    {
        $today = now()->format('Ymd');
        $count = InventoryAdjustment::query()->whereDate('created_at', today())->count() + 1;

        return sprintf('ADJ-%s-%05d', $today, $count);
    }
}
