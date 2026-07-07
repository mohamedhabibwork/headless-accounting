<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions\Inventory;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Inventory\SerialService;
use Headless\Accounting\Models\SerialEvent;
use Headless\Accounting\Models\SerialNumber;

/**
 * RecordSerialEvent — wraps {@see SerialService::recordEvent()} inside
 * a transaction so domain callers can treat a single lifecycle event
 * as an atomic operation.
 */
final class RecordSerialEvent extends Action
{
    public function __construct(private readonly SerialService $serials) {}

    /**
     * @param  array<string,mixed>  $context
     */
    protected function handle(
        SerialNumber $serial,
        string $event,
        array $context = [],
    ): SerialEvent {
        return $this->serials->recordEvent(
            $serial,
            $event,
            $context['from_status'] ?? null,
            $context['to_status'] ?? null,
            $context,
        );
    }
}
