<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Discounts\Drivers\DiscountDriver;
use Headless\Accounting\Exceptions\ConfigurationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Discount extends BaseModel
{
    protected string $tableSuffix = 'discounts';

    protected $fillable = [
        'name', 'code', 'type',
        'active', 'stackable', 'priority',
        'config', 'starts_at', 'ends_at',
        'channel_code', 'owner_type', 'owner_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'stackable' => 'boolean',
        'priority' => 'integer',
        'config' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(DiscountTarget::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(DiscountCondition::class)->orderBy('position');
    }

    public function limitations(): HasMany
    {
        return $this->hasMany(DiscountLimitation::class)->orderBy('position');
    }

    public function usages(): MorphMany
    {
        return $this->morphMany(DiscountUsage::class, 'source');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Returns a fully-built discount driver bound to this record.
     *
     * @throws ConfigurationException
     */
    public function driver(): DiscountDriver
    {
        $class = $this->type;

        if (! class_exists($class)) {
            throw new ConfigurationException(
                "Discount driver {$class} does not exist."
            );
        }
        $instance = app($class);
        if (! $instance instanceof DiscountDriver) {
            throw new ConfigurationException(
                "{$class} must implement DiscountDriver."
            );
        }
        $instance->setConfig((array) $this->config);

        return $instance;
    }

    public function isCoupon(): bool
    {
        return (bool) $this->code;
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->active) {
            return false;
        }
        $now = now();
        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }
}
