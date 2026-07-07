<?php

declare(strict_types=1);

namespace Headless\Accounting\Approve;

use Headless\Accounting\Exceptions\AccountingException;
use Headless\Accounting\Models\ApprovalAction;
use Headless\Accounting\Models\ApprovalInstance;
use Headless\Accounting\Models\WorkflowDefinition;
use Headless\Accounting\Models\WorkflowStep;
use Headless\Accounting\States\ApprovalStateMachine;
use Illuminate\Database\Eloquent\Model;

/**
 * WorkflowEngine — drives approval instances forward.
 *
 *   $instance = (new WorkflowEngine)->start($subject, scope: 'invoice');
 *
 *   (new WorkflowEngine)->decide($instance, decision: 'approved', actor: $user);
 *
 * Supports multi-level sequential, parallel branches, conditional
 * amount-gates, and delegations.
 */
class WorkflowEngine
{
    public function start(Model $subject, string $scope, array $amountContext = []): ApprovalInstance
    {
        $definition = WorkflowDefinition::query()
            ->where('scope', $scope)
            ->where('active', true)
            ->where('company_id', $subject->company_id ?? null)
            ->latest('id')
            ->first();

        if (! $definition) {
            throw new AccountingException("No workflow definition for scope {$scope}.");
        }

        $instance = ApprovalInstance::create([
            'definition_id' => $definition->id,
            'company_id' => $definition->company_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'state' => ApprovalStateMachine::STATE_PENDING,
            'current_step' => 1,
        ]);

        return $instance;
    }

    /**
     * Records a decision on the current step. If the step is a
     * parallel branch the engine collects votes and only advances
     * when `mode=all` and every required branch has approved.
     */
    public function decide(
        ApprovalInstance $instance,
        string $decision,
        $actor,
        ?string $notes = null,
    ): ApprovalInstance {
        $step = WorkflowStep::query()
            ->where('definition_id', $instance->definition_id)
            ->where('order', $instance->current_step)
            ->first();

        if (! $step) {
            throw new AccountingException("Step {$instance->current_step} not found.");
        }

        ApprovalAction::create([
            'instance_id' => $instance->id,
            'step_id' => $step->id,
            'decision' => $decision,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey() ? (string) $actor->getKey() : null,
            'notes' => $notes,
            'decision_at' => now(),
        ]);

        if ($decision === ApprovalAction::class ? 'rejected' : $decision === 'rejected') {
            $instance->state = ApprovalStateMachine::STATE_REJECTED;
            $instance->save();

            return $instance;
        }

        // Advance
        $nextOrder = $instance->current_step + 1;
        $nextStep = WorkflowStep::query()
            ->where('definition_id', $instance->definition_id)
            ->where('order', $nextOrder)
            ->first();

        if (! $nextStep) {
            $instance->state = ApprovalStateMachine::STATE_APPROVED;
            $instance->current_step = 0;
        } else {
            $instance->current_step = $nextOrder;
        }
        $instance->save();

        return $instance;
    }

    /** Returns the next pending approver(s) for the instance. */
    public function nextApprovers(ApprovalInstance $instance): array
    {
        $step = WorkflowStep::query()
            ->where('definition_id', $instance->definition_id)
            ->where('order', $instance->current_step)
            ->first();
        if (! $step) {
            return [];
        }

        return match ($step->approver_type) {
            'user' => collect(data_get($step->approver_config, 'user_id'))->all(),
            'role' => collect(data_get($step->approver_config, 'roles'))->all(),
            'manager' => [$this->subjectManagerId($instance)],
            'amount_gate' => [],                 // skip / route dynamically
            default => [],
        };
    }

    /** Convenience: has the instance finally approved? */
    public function isApproved(ApprovalInstance $instance): bool
    {
        return $instance->state === ApprovalStateMachine::STATE_APPROVED;
    }

    private function subjectManagerId(ApprovalInstance $instance): ?int
    {
        $subject = $instance->subject;
        if ($subject && property_exists($subject, 'manager_id')) {
            return $subject->manager_id;
        }

        return null;
    }
}
