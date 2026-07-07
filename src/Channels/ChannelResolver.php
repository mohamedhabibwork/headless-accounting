<?php

declare(strict_types=1);

namespace Headless\Accounting\Channels;

use Headless\Accounting\Exceptions\ConfigurationException;
use Headless\Accounting\Models\Channel;
use Headless\Accounting\Support\Config;
use Illuminate\Http\Request;

/**
 * ChannelResolver — picks the active sales channel for the current
 * request. Order of precedence:
 *
 *   1. `X-Channel` header (most explicit).
 *   2. `?channel=` query parameter.
 *   3. `channel()` session value (set by host-app middleware).
 *   4. `config('headless-accounting.channels.default')`.
 *
 * Always returns a real {@see Channel} Eloquent row, never just a code,
 * so callers can rely on currency, locale, tax zone, etc.
 */
final class ChannelResolver
{
    public function resolve(Request $request): Channel
    {
        $code = $request->header('X-Channel')
            ?? $request->query('channel')
            ?? $request->session()->get('channel')
            ?? Config::get('headless-accounting.channels.default');

        $channel = Channel::query()->where('code', $code)->where('active', true)->first();
        if (! $channel) {
            throw new ConfigurationException("Channel {$code} is not registered or inactive.");
        }

        return $channel;
    }
}
