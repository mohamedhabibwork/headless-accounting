<?php

declare(strict_types=1);

namespace Headless\Accounting\Channels;

use Headless\Accounting\Models\Channel;
use Headless\Accounting\Support\Config;

/**
 * ChannelContext — process-scoped holder for the currently active
 * channel. Useful for queues where the original HTTP request isn't
 * available: the queue middleware pushes the channel before
 * dispatching and pops it after.
 *
 *   ChannelContext::set($channel);      // start of job
 *   $channel = ChannelContext::current();
 *   // ...
 *   ChannelContext::forget();
 */
final class ChannelContext
{
    private static ?Channel $current = null;

    public static function set(Channel $channel): void
    {
        self::$current = $channel;
    }

    public static function current(): ?Channel
    {
        return self::$current;
    }

    public static function code(): string
    {
        return self::$current?->code ?? Config::string('headless-accounting.channels.default');
    }

    public static function forget(): void
    {
        self::$current = null;
    }
}
