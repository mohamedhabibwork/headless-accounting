<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Controllers;

use Headless\Accounting\Approve\WorkflowEngine;
use Headless\Accounting\Http\Requests\DecideWorkflowInstanceRequest;
use Headless\Accounting\Http\Requests\StartWorkflowInstanceRequest;
use Headless\Accounting\Http\Requests\StoreWorkflowDefinitionRequest;
use Headless\Accounting\Http\Requests\StoreWorkflowDelegationRequest;
use Headless\Accounting\Http\Requests\StoreWorkflowStepRequest;
use Headless\Accounting\Http\Requests\UpdateWorkflowDefinitionRequest;
use Headless\Accounting\Http\Requests\UpdateWorkflowStepRequest;
use Headless\Accounting\Models\ApprovalAction;
use Headless\Accounting\Models\ApprovalInstance;
use Headless\Accounting\Models\WorkflowDefinition;
use Headless\Accounting\Models\WorkflowDelegation;
use Headless\Accounting\Models\WorkflowStep;
use Headless\Accounting\States\ApprovalStateMachine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * WorkflowController — REST surface for the multi-level approval
 * engine. Three aggregates are exposed:
 *
 *  - WorkflowDefinition + WorkflowStep (template authoring)
 *  - ApprovalInstance + ApprovalAction   (running approvals and audit log)
 *  - WorkflowDelegation                  (escalation rules)
 */
class WorkflowController extends Controller
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    // ---------------------------------------------------------
    // 1. Definitions (templates)
    // ---------------------------------------------------------

    public function indexDefinitions(): JsonResponse
    {
        $definitions = WorkflowDefinition::query()
            ->with('steps')
            ->orderByDesc('id')
            ->paginate();

        return $this->paginated($definitions);
    }

    public function showDefinition(int $definitionId): JsonResponse
    {
        $definition = WorkflowDefinition::query()->with('steps')->findOrFail($definitionId);

        return new JsonResponse($this->serializeDefinition($definition));
    }

    public function storeDefinition(StoreWorkflowDefinitionRequest $request): JsonResponse
    {
        $definition = WorkflowDefinition::query()->create([
            'company_id' => $request->input('company_id'),
            'scope' => (string) $request->validated('scope'),
            'name' => (string) $request->validated('name'),
            'description' => $request->validated('description'),
            'config' => (array) $request->validated('config', []),
            'active' => (bool) $request->boolean('active', true),
        ]);

        foreach ((array) $request->validated('steps', []) as $step) {
            WorkflowStep::query()->create(array_merge(
                ['definition_id' => $definition->id],
                $this->normalizeStep($step),
            ));
        }

        return new JsonResponse(
            $this->serializeDefinition($definition->fresh('steps')),
            201,
        );
    }

    public function updateDefinition(UpdateWorkflowDefinitionRequest $request, int $definitionId): JsonResponse
    {
        $definition = WorkflowDefinition::query()->findOrFail($definitionId);
        $definition->update($request->validated());

        return new JsonResponse($this->serializeDefinition($definition->fresh('steps')));
    }

    public function destroyDefinition(int $definitionId): JsonResponse
    {
        $definition = WorkflowDefinition::query()->findOrFail($definitionId);
        // Keep the row for audit; deactivate so no new instances spawn.
        $definition->update(['active' => false]);

        return new JsonResponse(['id' => $definition->id, 'active' => false]);
    }

    // ---------------------------------------------------------
    // 2. Steps (append / patch / delete on a definition)
    // ---------------------------------------------------------

    public function storeStep(StoreWorkflowStepRequest $request, int $definitionId): JsonResponse
    {
        $definition = WorkflowDefinition::query()->findOrFail($definitionId);

        $step = WorkflowStep::query()->create(array_merge(
            ['definition_id' => $definition->id],
            $this->normalizeStep($request->validated()),
        ));

        return new JsonResponse($this->serializeStep($step), 201);
    }

    public function updateStep(UpdateWorkflowStepRequest $request, int $stepId): JsonResponse
    {
        $step = WorkflowStep::query()->findOrFail($stepId);
        $step->update($request->validated());

        return new JsonResponse($this->serializeStep($step));
    }

    public function destroyStep(int $stepId): JsonResponse
    {
        $step = WorkflowStep::query()->findOrFail($stepId);
        $step->delete();

        return new JsonResponse(['ok' => true], 204);
    }

    // ---------------------------------------------------------
    // 3. Approval instances (the live workflows)
    // ---------------------------------------------------------

    public function indexInstances(Request $request): JsonResponse
    {
        $query = ApprovalInstance::query()
            ->with(['definition', 'subject'])
            ->orderByDesc('id');

        if ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }
        if ($request->filled('scope')) {
            $query->whereHas('definition', fn ($q) => $q->where('scope', $request->input('scope')));
        }
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', (string) $request->input('subject_id'));
        }

        return $this->paginated($query->paginate(), fn (ApprovalInstance $i) => $this->serializeInstance($i));
    }

    public function showInstance(int $instanceId): JsonResponse
    {
        $instance = ApprovalInstance::query()
            ->with(['definition.steps', 'subject', 'actions', 'actions.step', 'actions.actor'])
            ->findOrFail($instanceId);

        return new JsonResponse($this->serializeInstance($instance, withActions: true));
    }

    /**
     * Start a fresh instance. The request body supplies the polymorphic
     * subject (subject_type / subject_id) — we resolve it via morph map
     * and pass to the engine.
     */
    public function startInstance(StartWorkflowInstanceRequest $request): JsonResponse
    {
        $subject = $this->resolveSubject(
            (string) $request->validated('subject_type'),
            $request->validated('subject_id'),
        );

        $instance = $this->engine->start(
            subject: $subject,
            scope: (string) $request->validated('scope'),
            amountContext: (array) $request->validated('amount_context', []),
        );

        return new JsonResponse($this->serializeInstance($instance), 201);
    }

    public function decideInstance(DecideWorkflowInstanceRequest $request, int $instanceId): JsonResponse
    {
        $instance = ApprovalInstance::query()->findOrFail($instanceId);

        $actor = $request->user();
        $this->engine->decide(
            instance: $instance,
            decision: (string) $request->validated('decision'),
            actor: $actor,
            notes: $request->validated('notes'),
        );

        return new JsonResponse($this->serializeInstance(
            $instance->fresh(['definition.steps', 'subject', 'actions']),
            withActions: true,
        ));
    }

    public function cancelInstance(int $instanceId): JsonResponse
    {
        $instance = ApprovalInstance::query()->findOrFail($instanceId);

        if (in_array($instance->state, [
            ApprovalStateMachine::STATE_APPROVED,
            ApprovalStateMachine::STATE_REJECTED,
            ApprovalStateMachine::STATE_CANCELLED,
        ], true)) {
            return new JsonResponse(
                ['error' => "Instance already in terminal state {$instance->state}."],
                422,
            );
        }

        $instance->state = ApprovalStateMachine::STATE_CANCELLED;
        $instance->save();

        return new JsonResponse($this->serializeInstance($instance->fresh()));
    }

    public function indexInstanceActions(int $instanceId): JsonResponse
    {
        ApprovalInstance::query()->findOrFail($instanceId);

        $actions = ApprovalAction::query()
            ->with(['step', 'actor'])
            ->where('instance_id', $instanceId)
            ->orderBy('decision_at')
            ->get();

        return new JsonResponse([
            'data' => $actions->map(fn (ApprovalAction $a) => $this->serializeAction($a))->all(),
        ]);
    }

    /**
     * Inbox — list open instances whose current step is configured for the
     * authenticated actor. Useful as the "things waiting on me" view in
     * a back-office. Filter lives in-memory because the engine resolves
     * the next-approver list per step. Rows stream via {@see lazy()} so
     * we never hydrate every pending instance at once.
     */
    public function inbox(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor) {
            return new JsonResponse(['data' => []]);
        }

        $actorKey = (string) $actor->getKey();
        $actorRoles = $this->resolveActorRoles($actor);

        $rows = [];

        ApprovalInstance::query()
            ->where('state', ApprovalStateMachine::STATE_PENDING)
            ->with(['definition.steps', 'subject'])
            ->orderByDesc('id')
            ->lazy(500)
            ->each(function (ApprovalInstance $instance) use (&$rows, $actorKey, $actorRoles): void {
                $next = $this->engine->nextApprovers($instance);

                $matches = in_array($actorKey, $next, true)
                    || array_intersect($actorRoles, $next) !== [];

                if ($matches) {
                    $rows[] = $this->serializeInstance($instance);
                }
            });

        return new JsonResponse(['data' => $rows]);
    }

    public function indexActions(Request $request): JsonResponse
    {
        $query = ApprovalAction::query()
            ->with(['step', 'actor', 'instance'])
            ->orderByDesc('decision_at');

        if ($request->filled('instance_id')) {
            $query->where('instance_id', (int) $request->input('instance_id'));
        }
        if ($request->filled('decision')) {
            $query->where('decision', $request->input('decision'));
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_id', (string) $request->input('actor_id'));
        }

        return $this->paginated($query->paginate(), fn (ApprovalAction $a) => $this->serializeAction($a));
    }

    // ---------------------------------------------------------
    // 4. Delegations
    // ---------------------------------------------------------

    public function indexDelegations(Request $request): JsonResponse
    {
        $query = WorkflowDelegation::query()->orderByDesc('id');

        if ($request->filled('from_user_id')) {
            $query->where('from_user_id', (int) $request->input('from_user_id'));
        }
        if ($request->filled('to_user_id')) {
            $query->where('to_user_id', (int) $request->input('to_user_id'));
        }
        if ($request->boolean('only_active')) {
            $query->where('active', true);
        }

        return $this->paginated($query->paginate(), fn (WorkflowDelegation $d) => $this->serializeDelegation($d));
    }

    public function storeDelegation(StoreWorkflowDelegationRequest $request): JsonResponse
    {
        $delegation = WorkflowDelegation::query()->create([
            'from_user_id' => (int) $request->validated('from_user_id'),
            'to_user_id' => (int) $request->validated('to_user_id'),
            'scope_type' => $request->validated('scope_type'),
            'scope_id' => $request->validated('scope_id') !== null ? (string) $request->validated('scope_id') : null,
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
            'active' => (bool) $request->boolean('active', true),
        ]);

        return new JsonResponse($this->serializeDelegation($delegation), 201);
    }

    public function destroyDelegation(int $delegationId): JsonResponse
    {
        $delegation = WorkflowDelegation::query()->findOrFail($delegationId);
        $delegation->update(['active' => false]);

        return new JsonResponse(['id' => $delegation->id, 'active' => false]);
    }

    // ---------------------------------------------------------
    // Serializers
    // ---------------------------------------------------------

    private function serializeDefinition(WorkflowDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'company_id' => $definition->company_id,
            'scope' => $definition->scope,
            'name' => $definition->name,
            'description' => $definition->description,
            'config' => $definition->config,
            'active' => $definition->active,
            'steps' => $definition->steps->map(fn (WorkflowStep $s) => $this->serializeStep($s))->all(),
            'created_at' => $definition->created_at?->toIso8601String(),
        ];
    }

    private function serializeStep(WorkflowStep $step): array
    {
        return [
            'id' => $step->id,
            'definition_id' => $step->definition_id,
            'order' => $step->order,
            'name' => $step->name,
            'approver_type' => $step->approver_type,
            'approver_config' => $step->approver_config,
            'min_amount_minor' => $step->min_amount_minor,
            'max_amount_minor' => $step->max_amount_minor,
            'mode' => $step->mode,
            'required' => $step->required,
        ];
    }

    private function serializeInstance(ApprovalInstance $instance, bool $withActions = false): array
    {
        $payload = [
            'id' => $instance->id,
            'definition_id' => $instance->definition_id,
            'company_id' => $instance->company_id,
            'subject' => [
                'type' => $instance->subject_type,
                'id' => $instance->subject_id,
            ],
            'state' => $instance->state,
            'current_step' => $instance->current_step,
            'next_approvers' => $this->engine->nextApprovers($instance),
            'created_at' => $instance->created_at?->toIso8601String(),
        ];

        if ($withActions) {
            $payload['actions'] = $instance->actions
                ? $instance->actions->map(fn (ApprovalAction $a) => $this->serializeAction($a))->all()
                : ApprovalAction::query()
                    ->with(['step', 'actor'])
                    ->where('instance_id', $instance->id)
                    ->orderBy('decision_at')
                    ->get()
                    ->map(fn (ApprovalAction $a) => $this->serializeAction($a))
                    ->all();
        }

        return $payload;
    }

    private function serializeAction(ApprovalAction $action): array
    {
        return [
            'id' => $action->id,
            'instance_id' => $action->instance_id,
            'step_id' => $action->step_id,
            'decision' => $action->decision,
            'actor' => [
                'type' => $action->actor_type,
                'id' => $action->actor_id,
            ],
            'notes' => $action->notes,
            'decided_at' => $action->decision_at?->toIso8601String(),
        ];
    }

    private function serializeDelegation(WorkflowDelegation $delegation): array
    {
        return [
            'id' => $delegation->id,
            'from_user_id' => $delegation->from_user_id,
            'to_user_id' => $delegation->to_user_id,
            'scope' => [
                'type' => $delegation->scope_type,
                'id' => $delegation->scope_id,
            ],
            'starts_at' => optional($delegation->starts_at)->toDateString(),
            'ends_at' => optional($delegation->ends_at)->toDateString(),
            'active' => (bool) $delegation->active,
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function normalizeStep(array $step): array
    {
        return [
            'order' => (int) $step['order'],
            'name' => $step['name'],
            'approver_type' => $step['approver_type'],
            'approver_config' => (array) ($step['approver_config'] ?? []),
            'min_amount_minor' => $step['min_amount_minor'] ?? null,
            'max_amount_minor' => $step['max_amount_minor'] ?? null,
            'mode' => $step['mode'] ?? null,
            'required' => (bool) ($step['required'] ?? true),
        ];
    }

    private function resolveSubject(string $type, mixed $id): Model
    {
        $modelClass = Relation::getMorphedModel($type) ?? $type;
        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Unknown subject type {$type}.");
        }

        $model = $modelClass::query()->find($id);
        if (! $model) {
            throw new ModelNotFoundException("Subject {$type}#{$id} not found.");
        }

        return $model;
    }

    /**
     * Best-effort extraction of role identifiers from a generic actor
     * model. Returns an empty array when the actor has no `roles`
     * attribute / relation — keeps the inbox filter usable for hosts
     * that don't model roles on their auth class.
     *
     * @return array<int, string>
     */
    private function resolveActorRoles(Model $actor): array
    {
        $value = $actor->roles ?? null;
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (is_object($value) && method_exists($value, 'pluck')) {
            return array_values(array_map('strval', $value->pluck('name')->all()));
        }

        if (is_string($value) && $value !== '') {
            return [$value];
        }

        return [];
    }

    /**
     * @param  LengthAwarePaginator  $paginator
     * @param  callable(Model): array|null  $mapper
     */
    private function paginated($paginator, ?callable $mapper = null): JsonResponse
    {
        $mapper ??= fn ($model) => $model;

        return new JsonResponse([
            'data' => collect($paginator->items())->map($mapper)->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
