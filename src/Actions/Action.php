<?php

declare(strict_types=1);

namespace Headless\Accounting\Actions;

use Headless\Accounting\Events\ActionExecuted;
use Headless\Accounting\Events\ActionExecuting;
use Headless\Accounting\Exceptions\ActionCancelledException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Action — base class for every domain action. Wraps the execute()
 * method in a DB transaction by default (override for actions that
 * manage their own transactions).
 *
 * Subclasses declare their own `handle(...)` method with the typed
 * signature that best describes the inputs. `execute()` forwards to
 * `handle()` by spreading named arguments. Because `handle` is not
 * declared here, subclasses' `handle` is a new method (not an
 * override), so PHP's LSP / signature-compatibility check does not
 * apply and each action can keep its own typed signature.
 *
 * Two lifecycle events are dispatched around `handle()`:
 *
 *   - {@see ActionExecuting}  — fired before `handle()`, inside the
 *     transaction. Listeners may set `$event->cancel = true` to halt
 *     the action; `execute()` then throws an
 *     {@see ActionCancelledException} and the transaction rolls back.
 *
 *   - {@see ActionExecuted}   — fired after `handle()` returns
 *     successfully. Carries the original arguments and the value
 *     returned by `handle()`. Not dispatched if the action was
 *     cancelled or if `handle()` threw.
 *
 * Both events carry the FQCN of the concrete action class as their
 * `$action` property, so listeners can branch on `static::class` or
 * subscribe to a specific subclass.
 */
abstract class Action
{
    public function execute(mixed ...$args): mixed
    {
        return DB::transaction(function () use ($args) {
            $before = new ActionExecuting(static::class, $args);
            Event::dispatch($before);

            if ($before->cancel) {
                throw new ActionCancelledException(static::class);
            }

            $result = $this->handle(...$args);

            Event::dispatch(new ActionExecuted(static::class, $args, $result));

            return $result;
        });
    }
}
