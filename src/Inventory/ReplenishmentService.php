<?php

declare(strict_types=1);

namespace Headless\Accounting\Inventory;

use Headless\Accounting\Events\InventoryReplenishmentTriggered;
use Headless\Accounting\Models\ProductVariant;
use Headless\Accounting\Models\PurchaseRequest;
use Headless\Accounting\Models\StockItem;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Models\WarehouseReorderRule;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;

/**
 * ReplenishmentService — computes reorder proposals for variants whose
 * on-hand quantity drops below their reorder point, and optionally
 * generates draft PurchaseRequest rows for the proposals.
 */
final class ReplenishmentService
{
    public function __construct(private readonly InventoryPolicyService $policy) {}

    /**
     * @return Collection<int, array{
     *     variant_id:int,
     *     warehouse_id:int,
     *     current_on_hand:int,
     *     reorder_point:int,
     *     suggested_quantity:int,
     *     preferred_vendor:?string,
     *     lead_time_days:int
     * }>
     */
    public function proposals(int $companyId, ?int $warehouseId = null): Collection
    {
        $warehouses = Warehouse::query()
            ->when($warehouseId !== null, fn ($q) => $q->where('id', $warehouseId))
            ->where('company_id', $companyId)
            ->get();

        $out = collect();

        foreach ($warehouses as $warehouse) {
            $items = StockItem::query()
                ->where('location_id', $warehouse->location_id)
                ->get();

            foreach ($items as $item) {
                $proposal = $this->proposalForVariantAtWarehouse($item->variant_id, (int) $warehouse->id, (int) $item->on_hand);

                if ($proposal === null) {
                    continue;
                }

                $out->push($proposal);

                Event::dispatch(new InventoryReplenishmentTriggered(
                    variant: ProductVariant::find($item->variant_id),
                    warehouse: $warehouse,
                    currentOnHand: $proposal['current_on_hand'],
                    reorderPoint: $proposal['reorder_point'],
                    suggestedQuantity: $proposal['suggested_quantity'],
                ));
            }
        }

        return $out
            ->sortBy(fn ($row) => $row['current_on_hand'] / max($row['reorder_point'], 1))
            ->values();
    }

    /**
     * @return array{
     *     variant_id:int,
     *     warehouse_id:int,
     *     current_on_hand:int,
     *     reorder_point:int,
     *     suggested_quantity:int,
     *     preferred_vendor:?string,
     *     lead_time_days:int
     * }|null
     */
    public function proposalForVariant(int $variantId, int $warehouseId): ?array
    {
        $warehouse = Warehouse::query()->find($warehouseId);
        if (! $warehouse) {
            return null;
        }

        $item = StockItem::query()
            ->where('variant_id', $variantId)
            ->where('location_id', $warehouse->location_id)
            ->first();

        return $this->proposalForVariantAtWarehouse($variantId, $warehouseId, (int) ($item?->on_hand ?? 0));
    }

    /**
     * @return array{
     *     variant_id:int,
     *     warehouse_id:int,
     *     current_on_hand:int,
     *     reorder_point:int,
     *     suggested_quantity:int,
     *     preferred_vendor:?string,
     *     lead_time_days:int
     * }|null
     */
    private function proposalForVariantAtWarehouse(int $variantId, int $warehouseId, int $currentOnHand): ?array
    {
        $rule = WarehouseReorderRule::query()
            ->where('warehouse_id', $warehouseId)
            ->where('variant_id', $variantId)
            ->first();

        if ($rule) {
            $reorderPoint = (int) $rule->reorder_point;
            $suggestedQty = (int) $rule->reorder_quantity;
            $preferredVendor = $rule->preferred_vendor_code;
            $leadTime = (int) $rule->lead_time_days;
        } else {
            $variant = ProductVariant::query()->find($variantId);
            if (! $variant) {
                return null;
            }
            $reorderPoint = (int) ($variant->reorder_point ?? 0);
            $suggestedQty = (int) ($variant->reorder_quantity ?? 0);
            $preferredVendor = null;
            $leadTime = (int) ($variant->lead_time_days ?? 0);
        }

        if ($reorderPoint <= 0 || $currentOnHand >= $reorderPoint) {
            return null;
        }

        if ($suggestedQty <= 0) {
            $suggestedQty = max(1, $reorderPoint - $currentOnHand);
        }

        return [
            'variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'current_on_hand' => $currentOnHand,
            'reorder_point' => $reorderPoint,
            'suggested_quantity' => $suggestedQty,
            'preferred_vendor' => $preferredVendor,
            'lead_time_days' => $leadTime,
        ];
    }

    public function generateDraftPurchaseOrders(int $companyId): int
    {
        if (! $this->policy->replenishmentEnabled()) {
            return 0;
        }

        $proposals = $this->proposals($companyId);

        if (! $this->policy->autoCreateDraftPo()) {
            return $proposals->count();
        }

        $created = 0;
        foreach ($proposals as $proposal) {
            $number = sprintf(
                '%s-%s-%05d',
                Config::string('headless-accounting.number_prefixes.purchase_request', 'PR'),
                now()->format('Ymd'),
                PurchaseRequest::query()->whereDate('created_at', today())->count() + 1 + $created,
            );

            PurchaseRequest::create([
                'company_id' => $companyId,
                'number' => $number,
                'state' => 'draft',
                'lines' => [[
                    'variant_id' => $proposal['variant_id'],
                    'quantity' => $proposal['suggested_quantity'],
                    'preferred_vendor' => $proposal['preferred_vendor'],
                ]],
                'justification' => sprintf(
                    'Auto-replenishment: variant %d at warehouse %d (on_hand=%d, reorder_point=%d).',
                    $proposal['variant_id'],
                    $proposal['warehouse_id'],
                    $proposal['current_on_hand'],
                    $proposal['reorder_point'],
                ),
            ]);
            $created++;
        }

        return $created;
    }
}
