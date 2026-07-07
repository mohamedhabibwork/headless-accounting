<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Discount;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Models\Discount;
use Headless\Accounting\Models\DiscountCondition;
use Headless\Accounting\Models\DiscountLimitation;
use Headless\Accounting\Models\DiscountTarget;
use Illuminate\Database\Eloquent\Model;

/**
 * CreateDiscount — persists a Discount row together with its conditions,
 * limitations and polymorphic targets in a single transaction. The
 * shipped form is what the back-office UI calls when a marketer hits
 * "publish promotion".
 */
final class CreateDiscount extends Action
{
    protected function handle(
        string $name,
        string $type,
        array $config = [],
        bool $active = true,
        bool $stackable = true,
        int $priority = 100,
        ?string $code = null,
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?string $channelCode = null,
        array $targets = [],          // [['type' => 'order', 'id' => 1], …] — polymorphic
        array $conditions = [],       // [['type' => 'min_order_amount', 'config' => […]], …]
        array $limitations = [],      // [['type' => 'max_per_order', 'config' => […]], …]
        ?Model $owner = null,
    ): Discount {
        $discount = Discount::create([
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'active' => $active,
            'stackable' => $stackable,
            'priority' => $priority,
            'config' => $config,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'channel_code' => $channelCode,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]);

        foreach ($targets as $i => $t) {
            DiscountTarget::create([
                'discount_id' => $discount->id,
                'target_type' => $t['type'],
                'target_id' => $t['id'],
            ]);
        }

        foreach ($conditions as $i => $c) {
            DiscountCondition::create([
                'discount_id' => $discount->id,
                'type' => $c['type'],
                'config' => $c['config'] ?? [],
                'position' => $i,
            ]);
        }

        foreach ($limitations as $i => $l) {
            DiscountLimitation::create([
                'discount_id' => $discount->id,
                'type' => $l['type'],
                'config' => $l['config'] ?? [],
                'position' => $i,
            ]);
        }

        return $discount->fresh(['targets', 'conditions', 'limitations']);
    }
}
