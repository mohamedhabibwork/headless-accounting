<?php

declare(strict_types=1);

namespace Headless\Accounting\Inventory;

use Carbon\Carbon;
use Headless\Accounting\Enums\Inventory\BatchStatus;
use Headless\Accounting\Events\BatchExpired;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Batch;
use Headless\Accounting\Models\BatchStock;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * BatchService — manages lot/batch master data and per-(batch, location,
 * bin) stock buckets. The per-variant cost layers still live in
 * {@see CostMethods}; BatchStock.quantity mirrors StockItem.on_hand for
 * variant+batch+location so FEFO consumption can be picked at the batch
 * level without iterating every cost layer.
 *
 * Callers (Actions) are responsible for wrapping the operation in a DB
 * transaction.
 */
final class BatchService
{
    private function batchesTable(): string
    {
        return Config::string('headless-accounting.table_prefix', 'ha_').'batches';
    }

    private function batchStocksTable(): string
    {
        return Config::string('headless-accounting.table_prefix', 'ha_').'batch_stocks';
    }

    public function __construct(
        private readonly InventoryValuationService $valuation,
        private readonly InventoryPolicyService $policy,
    ) {}

    public function create(
        int $variantId,
        string $batchNumber,
        ?Carbon $manufacturingDate = null,
        ?Carbon $expirationDate = null,
        ?string $supplierBatchNumber = null,
        ?string $productionBatchNumber = null,
        array $attributes = [],
    ): Batch {
        return Batch::query()->updateOrCreate(
            ['variant_id' => $variantId, 'batch_number' => $batchNumber],
            [
                'company_id' => null,
                'manufacturing_date' => $manufacturingDate?->toDateString(),
                'expiration_date' => $expirationDate?->toDateString(),
                'supplier_batch_number' => $supplierBatchNumber,
                'production_batch_number' => $productionBatchNumber,
                'status' => BatchStatus::Active,
                'attributes' => $attributes ?: null,
            ],
        );
    }

    public function receive(
        Batch $batch,
        int $locationId,
        int $binId,
        int $quantity,
        int $unitCostMinor,
        string $currency,
        ?int $variantIdForLayers = null,
    ): BatchStock {
        $variant = ProductVariant::query()->findOrFail($variantIdForLayers ?? $batch->variant_id);

        $this->valuation->receipt($variant, $locationId, $quantity, $unitCostMinor, $currency);

        $batchStock = BatchStock::query()->firstOrCreate(
            [
                'batch_id' => $batch->id,
                'location_id' => $locationId,
                'bin_id' => $binId,
            ],
            [
                'quantity' => 0,
                'reserved' => 0,
                'currency' => $currency,
                'unit_cost_minor' => $unitCostMinor,
            ],
        );

        $batchStock->quantity = (int) $batchStock->quantity + $quantity;
        $batchStock->currency = $currency;
        $batchStock->unit_cost_minor = $batchStock->unit_cost_minor ?? $unitCostMinor;
        $batchStock->save();

        $stock = StockItem::query()
            ->where('variant_id', $batch->variant_id)
            ->where('location_id', $locationId)
            ->first();

        if ($stock) {
            StockMovement::create([
                'stock_item_id' => $stock->id,
                'reason' => 'receipt-batch',
                'quantity' => $quantity,
                'balance_after' => $stock->on_hand,
                'source_type' => $batch->getMorphClass(),
                'source_id' => $batch->id,
                'occurred_at' => now(),
            ]);
        }

        return $batchStock;
    }

    /**
     * @return list<array{batch_id:int,batch_stock_id:int,quantity:int,unit_cost_minor:int}>
     */
    public function consumeFefo(ProductVariant $variant, int $locationId, int $qty): array
    {
        $remaining = $qty;
        $out = [];

        $rows = BatchStock::query()
            ->whereHas('batch', function ($q) use ($variant) {
                $q->where('variant_id', $variant->id)
                    ->whereIn('status', [BatchStatus::Active]);
            })
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0)
            ->join($this->batchesTable(), $this->batchesTable().'.id', '=', $this->batchStocksTable().'.batch_id')
            ->orderByRaw("COALESCE({$this->batchesTable()}.expiration_date, '9999-12-31'::date) ASC")
            ->orderBy($this->batchesTable().'.id')
            ->select($this->batchStocksTable().'.*')
            ->get();

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $row->quantity);
            $row->quantity -= $take;
            $row->save();
            $out[] = [
                'batch_id' => (int) $row->batch_id,
                'batch_stock_id' => (int) $row->id,
                'quantity' => $take,
                'unit_cost_minor' => (int) ($row->unit_cost_minor ?? 0),
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new AccountingException(
                "Insufficient batch-tracked stock for variant {$variant->id} at location {$locationId} (short by {$remaining})."
            );
        }

        return $out;
    }

    public function quarantineExpiredBatches(): int
    {
        $today = now()->toDateString();
        $count = 0;

        $batches = Batch::query()
            ->where('status', BatchStatus::Active)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', $today)
            ->get();

        foreach ($batches as $batch) {
            $batch->status = BatchStatus::Expired;
            $batch->save();

            if ($this->policy->autoQuarantineExpired()) {
                BatchStock::query()
                    ->where('batch_id', $batch->id)
                    ->update(['reserved' => DB::raw($this->batchStocksTable().'.reserved')]);
            }

            Event::dispatch(new BatchExpired($batch));
            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, Batch>
     */
    public function findNearExpiry(int $withinDays): Collection
    {
        $today = now()->toDateString();
        $cutoff = now()->addDays($withinDays)->toDateString();

        return Batch::query()
            ->where('status', BatchStatus::Active)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', $today)
            ->whereDate('expiration_date', '<=', $cutoff)
            ->get();
    }
}
