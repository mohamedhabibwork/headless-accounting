<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Approve\WorkflowEngine;
use Headless\Accounting\Models\ApprovalInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasApprovals — drop-in trait for host-side aggregate roots that
 * should be routed through the package's `ApprovalInstance` /
 * `ApprovalAction` workflow (multi-step sign-off, delegation, audit
 * trail). Pair with {@see WorkflowEngine}.
 *
 * @mixin Model
 */
trait HasApprovals
{
    /** @return MorphMany<ApprovalInstance> */
    public function approvalInstances(): MorphMany
    {
        return $this->morphMany(
            ApprovalInstance::class,
            'subject'
        );
    }

    /** Returns the most recent in-flight or completed instance. */
    public function latestApprovalInstance(): ?ApprovalInstance
    {
        return $this->approvalInstances()->orderByDesc('id')->first();
    }
}
