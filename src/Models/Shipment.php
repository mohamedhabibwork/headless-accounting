<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends BaseModel
{
    public const STATE_PENDING = 'pending';

    public const STATE_PICKED = 'picked';

    public const STATE_PACKED = 'packed';

    public const STATE_SHIPPED = 'shipped';

    public const STATE_IN_TRANSIT = 'in_transit';

    public const STATE_DELIVERED = 'delivered';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_RETURNED = 'returned';

    protected string $tableSuffix = 'shipments';

    protected $fillable = [
        'number', 'order_id',
        'fulfillment_plan_id', 'pick_list_id', 'pack_station_id',
        'warehouse_id', 'carrier_id', 'shipping_rate_card_id',
        'carrier_code', 'service_code',
        'state',
        'carrier', 'tracking_number', 'tracking_url',
        'weight_grams',
        'length_mm', 'width_mm', 'height_mm',
        'cost_minor', 'currency',
        'items', 'customs', 'label_url',
        'shipped_at', 'delivered_at',
        'metadata',
    ];

    protected $casts = [
        'items' => 'array',
        'customs' => 'array',
        'metadata' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function trackingUrl(): ?string
    {
        if ($this->tracking_url) {
            return $this->tracking_url;
        }

        $template = $this->carrier?->tracking_url_template;

        if (! $template || ! $this->tracking_number) {
            return null;
        }

        return str_replace('{tracking}', $this->tracking_number, $template);
    }

    public function totalItems(): int
    {
        $total = 0;
        foreach ((array) $this->items as $line) {
            $total += (int) ($line['quantity'] ?? 0);
        }

        return $total;
    }

    public function isFullyDelivered(): bool
    {
        return $this->state === self::STATE_DELIVERED;
    }
}
