<?php

declare(strict_types=1);

use Headless\Accounting\Accounting\DefaultChartOfAccounts;
use Headless\Accounting\Accounting\Journal;
use Headless\Accounting\Actions\Stocktake\ApproveStocktake;
use Headless\Accounting\Actions\Stocktake\CancelStocktake;
use Headless\Accounting\Actions\Stocktake\CreateStocktake;
use Headless\Accounting\Actions\Stocktake\PostStocktake;
use Headless\Accounting\Actions\Stocktake\RecordCount;
use Headless\Accounting\Actions\Stocktake\SubmitStocktakeForApproval;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\CostLayer;
use Headless\Accounting\Models\InventoryAdjustment;
use Headless\Accounting\Models\JournalEntry;
use Headless\Accounting\Models\Location;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\Stocktake;
use Headless\Accounting\Models\StocktakeLine;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Tenancy\Company;

beforeEach(function () {
    (new DefaultChartOfAccounts)->install();

    $this->warehouse = Warehouse::factory()->create(['code' => 'WH-ST']);
    $this->warehouse->update([
        'location_id' => Location::create(['code' => 'WH-ST-LOC', 'name' => 'St'])->id,
    ]);
});

describe('CreateStocktake action', function () {
    it('opens a stocktake and prepopulates a line per SKU on hand', function () {
        $a = ProductVariant::factory()->create(['sku' => 'SKU-A']);
        $b = ProductVariant::factory()->create(['sku' => 'SKU-B']);

        $base = $this->warehouse->location_id;
        StockItem::create([
            'variant_id' => $a->id,
            'location_id' => $base,
            'on_hand' => 12,
        ]);
        StockItem::create([
            'variant_id' => $b->id,
            'location_id' => $base,
            'on_hand' => 7,
        ]);

        $stocktake = (new CreateStocktake)->execute(
            warehouse: $this->warehouse,
            scope: Stocktake::SCOPE_FULL,
        );

        expect($stocktake)->toBeInstanceOf(Stocktake::class);
        expect($stocktake->state)->toBe(Stocktake::STATE_DRAFT);
        expect($stocktake->lines)->toHaveCount(2);
        $qtys = $stocktake->lines->pluck('system_quantity')->all();
        expect($qtys)->toContain(12);
        expect($qtys)->toContain(7);
    });

    it('restricts prepopulation to the given variant ids when scope=variant', function () {
        $a = ProductVariant::factory()->create(['sku' => 'A']);
        $b = ProductVariant::factory()->create(['sku' => 'B']);

        $base = $this->warehouse->location_id;
        StockItem::create([
            'variant_id' => $a->id, 'location_id' => $base, 'on_hand' => 4,
        ]);
        StockItem::create([
            'variant_id' => $b->id, 'location_id' => $base, 'on_hand' => 9,
        ]);

        $stocktake = (new CreateStocktake)->execute(
            warehouse: $this->warehouse,
            scope: Stocktake::SCOPE_VARIANT,
            variantIds: [$a->id],
        );

        expect($stocktake->lines)->toHaveCount(1);
        expect((int) $stocktake->lines->first()->system_quantity)->toBe(4);
    });
});

describe('RecordCount action', function () {
    it('records a count and updates the variance on a pending line', function () {
        $variant = ProductVariant::factory()->create(['sku' => 'STK']);
        StockItemBootFor($variant, $this->warehouse->location_id, 30);
        $stocktake = (new CreateStocktake)->execute($this->warehouse);

        $line = (new RecordCount)->execute(
            stocktake: $stocktake,
            variantId: $variant->id,
            countedQuantity: 27,
            reason: 'damaged',
        );

        expect((int) $line->counted_quantity)->toBe(27);
        expect((int) $line->variance)->toBe(-3);
        expect($line->state)->toBe(StocktakeLine::STATE_COUNTED);
        expect($line->reason)->toBe('damaged');
        expect($stocktake->fresh()->state)->toBe(Stocktake::STATE_COUNTING);
    });

    it('supports recounts that bump the count_round counter', function () {
        $variant = ProductVariant::factory()->create();
        StockItemBootFor($variant, $this->warehouse->location_id, 5);
        $stocktake = (new CreateStocktake)->execute($this->warehouse);

        (new RecordCount)->execute($stocktake, $variant->id, 4);
        $recount = (new RecordCount)->execute($stocktake, $variant->id, 5, recount: true);

        expect((int) $recount->count_round)->toBe(2);
        expect((int) $recount->counted_quantity)->toBe(5);
        expect((int) $recount->variance)->toBe(0);
    });

    it('rejects negative counts', function () {
        $variant = ProductVariant::factory()->create();
        $stocktake = (new CreateStocktake)->execute($this->warehouse);

        expect(fn () => (new RecordCount)->execute($stocktake, $variant->id, -1))
            ->toThrow(AccountingException::class);
    });
});

describe('Stocktake state machine', function () {
    it('walks draft → counting → counted → under_review → approved', function () {
        $variant = ProductVariant::factory()->create();
        $stocktake = (new CreateStocktake)->execute($this->warehouse);

        (new RecordCount)->execute($stocktake, $variant->id, 9);
        expect($stocktake->fresh()->state)->toBe(Stocktake::STATE_COUNTING);

        $stocktake->update(['state' => Stocktake::STATE_COUNTED]);

        $submitted = (new SubmitStocktakeForApproval)->execute($stocktake);
        expect($submitted->state)->toBe(Stocktake::STATE_UNDER_REVIEW);

        $approved = (new ApproveStocktake)->execute($submitted);
        expect($approved->state)->toBe(Stocktake::STATE_APPROVED);
        expect($approved->approved_at)->not->toBeNull();
    });

    it('forbids approval while lines remain uncounted', function () {
        $variant = ProductVariant::factory()->create();
        StockItemBootFor($variant, $this->warehouse->location_id, 5);
        // Create stocktake (prepopulates one line) but never count it.
        $stocktake = (new CreateStocktake)->execute($this->warehouse);
        $stocktake->update(['state' => Stocktake::STATE_COUNTED]);

        expect(fn () => (new ApproveStocktake)->execute($stocktake))
            ->toThrow(AccountingException::class);
    });

    it('can be cancelled before posting', function () {
        $stocktake = (new CreateStocktake)->execute($this->warehouse);
        $cancelled = (new CancelStocktake)->execute($stocktake, reason: 'aborted');

        expect($cancelled->state)->toBe(Stocktake::STATE_CANCELLED);
        expect($cancelled->notes)->toContain('CANCELLED');
    });

    it('refuses to cancel after posting', function () {
        $stocktake = (new CreateStocktake)->execute($this->warehouse);
        $stocktake->update(['state' => Stocktake::STATE_POSTED, 'posted_at' => now()->toDateString()]);

        expect(fn () => (new CancelStocktake)->execute($stocktake))
            ->toThrow(AccountingException::class);
    });

    it('reports canTransitionTo correctly', function () {
        $stocktake = Stocktake::factory()->inState(Stocktake::STATE_COUNTING)->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($stocktake->canTransitionTo(Stocktake::STATE_UNDER_REVIEW))->toBeTrue();
        expect($stocktake->canTransitionTo(Stocktake::STATE_POSTED))->toBeFalse();

        $posted = Stocktake::factory()->inState(Stocktake::STATE_POSTED)->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        expect($posted->canTransitionTo(Stocktake::STATE_APPROVED))->toBeFalse();
    });
});

describe('PostStocktake action', function () {
    it('applies variances and posts a balanced journal entry', function () {
        $company = Company::create([
            'code' => 'POST', 'name' => 'Post Co', 'base_currency' => 'EUR',
        ]);
        $variant = ProductVariant::factory()->create(['sku' => 'POST']);
        StockItemBootFor($variant, $this->warehouse->location_id, 50);

        // Seed a cost layer for the unit cost lookup.
        CostLayer::create([
            'company_id' => $company->id,
            'variant_id' => $variant->id,
            'location_id' => $this->warehouse->location_id,
            'received_at' => now()->subWeek()->toDateString(),
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost_minor' => 200,
            'currency' => 'EUR',
            'source' => 'gr',
        ]);

        $stocktake = (new CreateStocktake)->execute($this->warehouse);
        $stocktake->update(['company_id' => $company->id]);

        // Counter finds 47.
        (new RecordCount)->execute($stocktake, $variant->id, 47);

        $stocktake->update(['state' => Stocktake::STATE_COUNTED]);
        (new SubmitStocktakeForApproval)->execute($stocktake);
        (new ApproveStocktake)->execute($stocktake);

        $posted = (new PostStocktake(app(Journal::class)))->execute($stocktake);

        expect($posted->state)->toBe(Stocktake::STATE_POSTED);
        expect($posted->posted_journal_entry_id)->not->toBeNull();

        $entry = JournalEntry::query()->find($posted->posted_journal_entry_id);
        expect($entry)->not->toBeNull();
        $entry->assertBalanced();

        // Stock should now be 47 in the matching stock item.
        $si = StockItem::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $this->warehouse->location_id)
            ->first();
        expect((int) $si->on_hand)->toBe(47);

        // Stocktake movement recorded.
        $movement = StockMovement::query()->where('reason', 'stocktake')->latest('id')->first();
        expect((int) $movement->quantity)->toBe(-3);

        // InventoryAdjustment created.
        expect(InventoryAdjustment::query()->where('reason', 'stocktake:'.$stocktake->number)->exists())->toBeTrue();
    });

    it('is idempotent — refuses to re-post', function () {
        $company = Company::create([
            'code' => 'IDEM', 'name' => 'Idem Co', 'base_currency' => 'EUR',
        ]);
        $variant = ProductVariant::factory()->create();
        StockItemBootFor($variant, $this->warehouse->location_id, 10);

        $stocktake = (new CreateStocktake)->execute($this->warehouse);
        $stocktake->update(['company_id' => $company->id]);
        (new RecordCount)->execute($stocktake, $variant->id, 10);
        $stocktake->update(['state' => Stocktake::STATE_COUNTED]);
        (new ApproveStocktake)->execute($stocktake);
        (new PostStocktake(app(Journal::class)))->execute($stocktake);

        expect(fn () => (new PostStocktake(app(Journal::class)))->execute($stocktake->fresh()))
            ->toThrow(AccountingException::class);
    });

    it('reports a variance summary with shortages and overages bucketed', function () {
        $a = ProductVariant::factory()->create();
        $b = ProductVariant::factory()->create();
        $c = ProductVariant::factory()->create();

        $st = Stocktake::factory()->inState(Stocktake::STATE_COUNTED)->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        foreach ([[$a, 10, 8], [$b, 10, 13], [$c, 5, 5]] as [$v, $sys, $cnt]) {
            StocktakeLine::create([
                'stocktake_id' => $st->id,
                'variant_id' => $v->id,
                'system_quantity' => $sys,
                'counted_quantity' => $cnt,
                'variance' => $cnt - $sys,
                'count_round' => 1,
                'state' => StocktakeLine::STATE_COUNTED,
                'unit_cost_minor' => 100,
                'variance_value_minor' => 100 * ($cnt - $sys),
                'currency' => 'EUR',
            ]);
        }

        $summary = $st->fresh()->varianceSummary();

        expect($summary['short_count'])->toBe(1);
        expect($summary['over_count'])->toBe(1);
        expect($summary['short_value_minor'])->toBe(200);
        expect($summary['over_value_minor'])->toBe(300);
        expect($summary['net_value_minor'])->toBe(100);
    });
});

/**
 * Helper: seed a StockItem with a given on-hand balance at a warehouse location.
 */
function StockItemBootFor($variant, int $locationId, int $onHand): void
{
    StockItem::create([
        'variant_id' => $variant->id,
        'location_id' => $locationId,
        'on_hand' => $onHand,
    ]);
}
