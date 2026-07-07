<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\Channel;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToChannel
{
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_code', 'code');
    }

    public function getChannelCode(): string
    {
        return $this->channel_code ?? Config::string('headless-accounting.channels.default');
    }

    public function scopeForChannel($query, string $code)
    {
        return $query->where('channel_code', $code);
    }
}
