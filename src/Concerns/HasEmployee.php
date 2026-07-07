<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * HasEmployee — drop-in trait for a host-side user model to expose
 * a polymorphic 1-to-1 link to the package's {@see Employee}
 * table. Hosts use it in HR / payroll integrations where the SaaS
 * user is also a payrolled employee.
 *
 * @mixin Model
 */
trait HasEmployee
{
    public function employee(): MorphOne
    {
        return $this->morphOne(
            Employee::class,
            'owner'
        );
    }

    /**
     * Eager-load friendly alias and the resolved model getter.
     * Property access (`$user->employee`) returns the resolved model.
     */
    public function getEmployeeAttribute(): ?Employee
    {
        return $this->employee()->first();
    }

    public function getOrCreateEmployee(): Employee
    {
        return $this->employee()->firstOrCreate([]);
    }
}
