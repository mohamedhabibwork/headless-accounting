<?php

declare(strict_types=1);

namespace Headless\Accounting\Events;

use Headless\Accounting\Actions\Action;
use Headless\Accounting\Exceptions\ActionCancelledException;

/**
 * Dispatched by {@see Action::execute()} right
 * before the action's `handle()` method is invoked. Listeners may inspect
 * or mutate the inputs by reference and, when necessary, halt the action
 * by setting `$event->cancel = true`.
 *
 *   Event::listen(ActionExecuting::class, function (ActionExecuting $e) {
 *       if ($e->action === CreateOrder::class && someBlocker($e->args)) {
 *           $e->cancel = true;
 *       }
 *   });
 *
 * Setting `cancel` aborts the action and throws an
 * {@see ActionCancelledException}; the
 * outer DB transaction started by `execute()` is rolled back as usual.
 */
class ActionExecuting extends Event
{
    public bool $cancel = false;

    /**
     * @param  string  $action  FQCN of the action class that is about to run.
     * @param  array<string, mixed>  $args  Named arguments passed to execute().
     */
    public function __construct(
        public readonly string $action,
        public readonly array $args,
    ) {}
}
