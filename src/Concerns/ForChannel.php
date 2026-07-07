<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Channel;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ForChannel — drop-in trait for host-side models that participate in
 * the package's multi-channel layer. Pairs with {@see BelongsToChannel}
 * (which expects the package's `Channel` model) but allows the host
 * to override the relation's foreign key name.
 *
 * Use this when:
 *   - the host owns a custom Channel implementation
 *   - the host's rows use a different column name (e.g. `sales_channel_id`)
 *
 * Schema expected:
 *
 *     $table->string('channel_code')->index();   // matches Channel.code
 *
 * @mixin Model
 */
trait ForChannel
{
    public function channel(): BelongsTo
    {
        return $this->belongsTo(
            Channel::class,
            $this->channelForeignKey(),
            'code'
        );
    }

    public function getChannelCode(): string
    {
        $value = $this->attributes[$this->channelForeignKey()] ?? null;

        return (string) ($value ?: Config::string(
            'headless-accounting.channels.default'
        ));
    }

    public function scopeForChannel($query, string $code)
    {
        return $query->where($this->channelForeignKey(), $code);
    }

    public function scopeForDefaultChannel($query)
    {
        return $query->where(
            $this->channelForeignKey(),
            Config::string('headless-accounting.channels.default')
        );
    }

    protected function channelForeignKey(): string
    {
        return property_exists($this, 'channelForeignKey')
            ? $this->channelForeignKey
            : 'channel_code';
    }
}
