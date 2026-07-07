<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Actions\Action;

/**
 * Dispatched by {@see Action::execute()} right
 * after the action's `handle()` method returns successfully. The event
 * exposes the original arguments and the value returned by `handle()`.
 *
 * Fires only on the happy path; if `handle()` throws (or a
 * {@see ActionExecuting} listener cancels the action), this event is not
 * dispatched and the surrounding DB transaction is rolled back.
 *
 *   Event::listen(ActionExecuted::class, function (ActionExecuted $e) {
 *       Log::info("{$e->action} produced", (array) $e->result);
 *   });
 */
class ActionExecuted extends Event
{
    /**
     * @param  string  $action  FQCN of the action class that just ran.
     * @param  array<string, mixed>  $args  Named arguments passed to execute().
     * @param  mixed  $result  Whatever `handle()` returned.
     */
    public function __construct(
        public readonly string $action,
        public readonly array $args,
        public readonly mixed $result,
    ) {}
}
