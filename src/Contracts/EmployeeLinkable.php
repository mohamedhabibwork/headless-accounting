<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Concerns\HasEmployee;
use Headless\Accounting\HR\Employee;

/**
 * EmployeeLinkable — host-side contract for any model that should be
 * linked to a package-side {@see Employee}
 * row. Useful when the host's User model carries HR-relevant fields
 * (start date, manager, …) and you want a single onboarding pipeline.
 *
 * Returns nullable: an Employee is provisioned lazily.
 *
 * The interface declares `employee()` without a return type so that
 * the {@see HasEmployee} trait's eager-load
 * friendly `MorphOne` can satisfy it alongside hosts that prefer a
 * strict `?Employee` return type on their own implementation.
 */
interface EmployeeLinkable
{
    public function employee();
}
