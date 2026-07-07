<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Fulfillment;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\ShipmentShipped;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\Carrier;
use Headless\Accounting\Models\FulfillmentPlan;
use Headless\Accounting\Models\Order;
use Headless\Accounting\Models\PackStation;
use Headless\Accounting\Models\PickList as PickListModel;
use Headless\Accounting\Models\Shipment;
use Headless\Accounting\Models\ShippingRateCard;
use Headless\Accounting\Models\StockMovement;
use Headless\Accounting\Models\Warehouse;
use Headless\Accounting\Support\Config;

/**
 * ShipOrder — converts a packed {@see PackStation} (and its source
 * {@see PickList}) into a {@see Shipment} ready for hand-off to the
 * carrier. Decrements `reserved` on the corresponding stock items,
 * writes a `ship` StockMovement, and updates the parent
 * {@see FulfillmentPlan} state.
 */
final class ShipOrder extends Action
{
    protected function handle(
        PackStation $packStation,
        string $carrierCode,
        string $serviceCode,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
        ?string $labelUrl = null,
        ?string $costMinor = null,
        ?string $currency = null,
    ): Shipment {
        $pickList = $packStation->pickList;
        if (! $pickList) {
            throw new AccountingException('Pack station is missing its pick list.');
        }
        $plan = $pickList->fulfillmentPlan;
        if (! $plan) {
            throw new AccountingException('Pick list is missing its fulfillment plan.');
        }
        $warehouse = $pickList->warehouse;
        if (! $warehouse) {
            throw new AccountingException('Pick list has no warehouse.');
        }

        $carrier = Carrier::query()->where('code', $carrierCode)->first();
        if (! $carrier) {
            throw new AccountingException("Unknown carrier code: {$carrierCode}");
        }

        $rateCard = $this->resolveRateCard($carrier, $warehouse, $serviceCode, $packStation);
        $items = $packStation->items ?? [];
        $country = (string) data_get($plan->order?->shipping_address_snapshot, 'country', '');
        $weight = $packStation->billableWeightGrams();
        $shippingCostMinor = $costMinor !== null
            ? (int) $costMinor
            : (int) ($rateCard?->quote($country, $weight, (int) ($plan->order?->grand_total_minor ?? 0))['cost_minor'] ?? 0);
        $shippingCurrency = $currency ?: ($rateCard?->currency ?? 'EUR');

        $shipment = Shipment::create([
            'number' => $this->nextNumber(),
            'order_id' => $plan->order_id,
            'fulfillment_plan_id' => $plan->id,
            'pick_list_id' => $pickList->id,
            'pack_station_id' => $packStation->id,
            'warehouse_id' => $warehouse->id,
            'carrier_id' => $carrier->id,
            'shipping_rate_card_id' => $rateCard?->id,
            'carrier_code' => $carrier->code,
            'service_code' => $serviceCode,
            'state' => Shipment::STATE_SHIPPED,
            'carrier' => $carrier->name,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
            'weight_grams' => $weight,
            'length_mm' => $packStation->length_mm,
            'width_mm' => $packStation->width_mm,
            'height_mm' => $packStation->height_mm,
            'cost_minor' => $shippingCostMinor,
            'currency' => $shippingCurrency,
            'items' => $items,
            'label_url' => $labelUrl,
            'shipped_at' => now(),
        ]);

        // Move reserved stock down to reflect the outgoing shipment.
        foreach ($pickList->lines()->get() as $line) {
            $stockItem = $line->stockItem;
            if (! $stockItem) {
                continue;
            }
            $picked = (int) $line->quantity_picked;
            if ($picked <= 0) {
                continue;
            }

            $stockItem->reserved = max(0, (int) $stockItem->reserved - $picked);
            $stockItem->save();

            StockMovement::create([
                'stock_item_id' => $stockItem->id,
                'reason' => 'ship',
                'quantity' => -$picked,
                'balance_after' => $stockItem->on_hand,
                'source_type' => $shipment->getMorphClass(),
                'source_id' => $shipment->id,
                'occurred_at' => now(),
            ]);
        }

        $packStation->state = PackStation::STATE_SHIPPED;
        $packStation->sealed_at = now();
        $packStation->save();

        $this->advancePlan($plan);

        ShipmentShipped::dispatch($shipment);

        return $shipment;
    }

    protected function resolveRateCard(Carrier $carrier, Warehouse $warehouse, string $serviceCode, PackStation $pack): ?ShippingRateCard
    {
        return $carrier->rateCards()
            ->where('active', true)
            ->where('service_code', $serviceCode)
            ->where(function ($q) use ($warehouse) {
                $q->whereNull('warehouse_id')->orWhere('warehouse_id', $warehouse->id);
            })
            ->orderByRaw('warehouse_id IS NULL')
            ->first();
    }

    protected function advancePlan(FulfillmentPlan $plan): void
    {
        $openPickLists = $plan->pickLists()
            ->whereNotIn('state', [PickListModel::STATE_PACKED, PickListModel::STATE_CANCELLED])
            ->count();

        $plan->state = $openPickLists > 0
            ? FulfillmentPlan::STATE_PARTIAL
            : FulfillmentPlan::STATE_SHIPPED;
        $plan->completed_at = $plan->state === FulfillmentPlan::STATE_SHIPPED ? now() : null;
        $plan->save();

        $order = $plan->order;
        if ($order) {
            $order->refresh();
            $allShipped = $order->shipments()->where('state', Shipment::STATE_SHIPPED)->exists()
                && $order->shipments()->where('state', Shipment::STATE_PENDING)->doesntExist();
            if ($allShipped) {
                $order->state = Order::STATE_FULFILLED;
                $order->fulfilled_at = now();
                $order->save();
            } elseif ($order->shipments()->where('state', Shipment::STATE_SHIPPED)->exists()) {
                $order->state = Order::STATE_PARTIALLY_FULFILLED;
                $order->save();
            }
        }
    }

    protected function nextNumber(): string
    {
        $today = now()->format('Ymd');
        $count = Shipment::query()->whereDate('created_at', today())->count() + 1;
        $prefix = Config::string('headless-accounting.number_prefixes.shipment', 'SH');

        return sprintf('%s-%s-%05d', $prefix, $today, $count);
    }
}
