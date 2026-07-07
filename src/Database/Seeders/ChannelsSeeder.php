<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Seeders;

use Headless\Accounting\Models\Channel;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Seeder;

/**
 * ChannelsSeeder — installs every channel listed in the config's
 * `channels.list` block. Idempotent — re-running changes nothing.
 */
class ChannelsSeeder extends Seeder
{
    public function run(): void
    {
        $list = Config::array('headless-accounting.channels.list', []);
        foreach ($list as $code => $spec) {
            Channel::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $spec['name'] ?? ucfirst($code),
                    'currency' => $spec['currency'] ?? Config::string('headless-accounting.currency.default'),
                    'locale' => $spec['locale'] ?? Config::string('headless-accounting.locale.default'),
                    'tax_zone_code' => $spec['tax_zone_code'] ?? null,
                    'tax_inclusive' => (bool) ($spec['tax_inclusive'] ?? false),
                    'active' => (bool) ($spec['active'] ?? true),
                    'config' => $spec['config'] ?? null,
                ],
            );
        }
    }
}
