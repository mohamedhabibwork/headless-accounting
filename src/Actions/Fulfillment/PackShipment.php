<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Fulfillment;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\ShipmentPacked;
use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\PackStation;
use Headless\Accounting\Models\PickList;
use Headless\Accounting\Support\Config;

/**
 * PackShipment — records the packing step: validates the pick list is
 * fully picked (no shortage lines except optionally allowed), creates a
 * {@see PackStation} record with carton info, and seals it for hand-off
 * to the carrier.
 */
final class PackShipment extends Action
{
    protected function handle(
        PickList $pickList,
        string $cartonType,
        float $weightGrams,
        float $lengthMm,
        float $widthMm,
        float $heightMm,
        ?string $packerName = null,
        bool $allowShortages = false,
    ): PackStation {
        if (! $allowShortages && $pickList->hasShortages()) {
            throw new AccountingException(
                "Pick list {$pickList->number} has short lines; pack with --allow-shortages to override."
            );
        }

        $items = [];
        foreach ($pickList->lines()->get() as $line) {
            $items[] = [
                'variant_id' => $line->variant_id,
                'sku' => $line->variant?->sku,
                'requested' => (int) $line->quantity_requested,
                'picked' => (int) $line->quantity_picked,
                'quantity' => (int) $line->quantity_picked,
            ];
        }

        $pack = PackStation::create([
            'pick_list_id' => $pickList->id,
            'number' => $this->nextNumber(),
            'packer_name' => $packerName,
            'carton_type' => $cartonType,
            'weight_grams' => $weightGrams,
            'length_mm' => $lengthMm,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'items' => $items,
            'state' => PackStation::STATE_PACKED,
            'packed_at' => now(),
        ]);

        $pickList->state = PickList::STATE_PACKED;
        $pickList->completed_at = now();
        $pickList->save();

        ShipmentPacked::dispatch($pack);

        return $pack;
    }

    protected function nextNumber(): string
    {
        $today = now()->format('Ymd');
        $count = PackStation::query()->whereDate('created_at', today())->count() + 1;
        $prefix = Config::string('headless-accounting.number_prefixes.pack_station', 'PK');

        return sprintf('%s-%s-%05d', $prefix, $today, $count);
    }
}
