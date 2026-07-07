<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Contracts\NumberSeries;
use Illuminate\Support\Carbon;

class FakeNumberSeries implements NumberSeries
{
    /** @var array<string, int> */
    public array $counters = [];

    public function next(string $type, string $model, array $options = []): string
    {
        $this->counters[$type] = ($this->counters[$type] ?? 0) + 1;

        $year = (string) ($options['year'] ?? Carbon::now()->format('Y'));
        $prefix = (string) ($options['prefix'] ?? strtoupper($type));
        $pad = (int) ($options['padding'] ?? 6);

        return sprintf('%s-%s-%0'.$pad.'d', $prefix, $year, $this->counters[$type]);
    }

    public function nextDaily(string $type, string $model, array $options = []): string
    {
        $this->counters[$type] = ($this->counters[$type] ?? 0) + 1;

        $prefix = (string) ($options['prefix'] ?? strtoupper($type));
        $day = Carbon::now()->format('Ymd');
        $pad = (int) ($options['padding'] ?? 5);

        return sprintf('%s-%s-%0'.$pad.'d', $prefix, $day, $this->counters[$type]);
    }

    public function matchesFormat(string $type, string $candidate): bool
    {
        return (bool) preg_match('/^[A-Z]+-\d{4}-\d+$/', $candidate);
    }
}
