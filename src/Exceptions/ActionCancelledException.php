<?php

declare(strict_types=1);

namespace Headless\Accounting\Exceptions;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Events\ActionExecuting;

/**
 * Thrown when a listener on {@see ActionExecuting}
 * sets `$event->cancel = true`, signalling that the action must not run.
 *
 * The exception extends {@see AccountingException} so callers that already
 * catch that base type continue to work unchanged. The outer DB transaction
 * that {@see Action::execute()} opens is
 * rolled back automatically.
 */
class ActionCancelledException extends AccountingException
{
    public function __construct(public readonly string $actionClass)
    {
        parent::__construct("Action [{$actionClass}] was cancelled by an ActionExecuting listener.");
    }
}
