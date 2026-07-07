<?php

declare(strict_types=1);

namespace Headless\Accounting\Inventory;

use Carbon\Carbon;
use Headless\Accounting\Enums\Inventory\SerialNumberStatus;
use Headless\Accounting\Models\SerialEvent;
use Headless\Accounting\Models\SerialNumber;

/**
 * SerialService — manages per-unit serial numbers and their lifecycle
 * events. Receivers can use {@see SerialService::recordEvent} directly
 * for custom flows; the convenience methods wrap the most common
 * transitions.
 *
 * Callers (Actions) are responsible for wrapping the operation in a DB
 * transaction.
 */
final class SerialService
{
    public function register(
        int $variantId,
        string $serial,
        ?int $batchId = null,
        ?Carbon $manufacturingDate = null,
        ?Carbon $warrantyExpiresAt = null,
        array $warrantyTerms = [],
        ?int $locationId = null,
        ?int $binId = null,
    ): SerialNumber {
        return SerialNumber::query()->updateOrCreate(
            ['variant_id' => $variantId, 'serial' => $serial],
            [
                'batch_id' => $batchId,
                'manufacturing_date' => $manufacturingDate?->toDateString(),
                'warranty_expires_at' => $warrantyExpiresAt?->toDateString(),
                'warranty_terms' => $warrantyTerms ?: null,
                'location_id' => $locationId,
                'bin_id' => $binId,
                'status' => SerialNumberStatus::InStock,
            ],
        );
    }

    public function assign(SerialNumber $serial, int $customerId, ?string $note = null): SerialEvent
    {
        $from = $this->statusValue($serial->status);

        $serial->status = SerialNumberStatus::Sold;
        $serial->assigned_to_customer_id = $customerId;
        $serial->sold_at = now();
        $serial->save();

        return $this->recordEvent($serial, 'sold', $from, SerialNumberStatus::Sold->value, [
            'customer_id' => $customerId,
            'note' => $note,
        ]);
    }

    public function markReturned(SerialNumber $serial, ?int $newLocationId = null, ?string $note = null): SerialEvent
    {
        $from = $this->statusValue($serial->status);

        $serial->status = SerialNumberStatus::Returned;
        if ($newLocationId !== null) {
            $serial->location_id = $newLocationId;
        }
        $serial->save();

        return $this->recordEvent($serial, 'returned', $from, SerialNumberStatus::Returned->value, [
            'location_id' => $newLocationId,
            'note' => $note,
        ]);
    }

    public function markUnderRepair(SerialNumber $serial, ?string $note = null): SerialEvent
    {
        $from = $this->statusValue($serial->status);

        $serial->status = SerialNumberStatus::UnderRepair;
        $serial->save();

        return $this->recordEvent($serial, 'repaired', $from, SerialNumberStatus::UnderRepair->value, [
            'note' => $note,
        ]);
    }

    public function retire(SerialNumber $serial, ?string $note = null): SerialEvent
    {
        $from = $this->statusValue($serial->status);

        $serial->status = SerialNumberStatus::Retired;
        $serial->save();

        return $this->recordEvent($serial, 'retired', $from, SerialNumberStatus::Retired->value, [
            'note' => $note,
        ]);
    }

    private function statusValue(SerialNumberStatus|string|null $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return $status instanceof SerialNumberStatus ? $status->value : $status;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function recordEvent(
        SerialNumber $serial,
        string $event,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        array $context = [],
    ): SerialEvent {
        $locationId = $context['location_id'] ?? $serial->location_id;
        $customerId = $context['customer_id'] ?? $serial->assigned_to_customer_id;

        return SerialEvent::create([
            'serial_number_id' => $serial->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus ?? $this->statusValue($serial->status),
            'location_id' => $locationId,
            'customer_id' => $customerId,
            'note' => $context['note'] ?? null,
            'occurred_at' => now(),
        ]);
    }
}
