<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Group;
use App\Models\Ticket;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project as NajmBaharProject;
use Illuminate\Support\Str;

class NajmHodaUnifiedDomainKnowledgeGraphService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function query(array $query): array
    {
        $traceId = (string) Str::uuid();
        $actorId = isset($query['actor_id']) ? (int) $query['actor_id'] : 0;
        $requestedScope = trim((string) ($query['scope'] ?? 'actor'));
        $limit = max(1, min(100, (int) ($query['limit'] ?? 20)));
        $profile = trim((string) ($query['profile'] ?? 'overview'));
        $profileConfig = $this->resolveProfile($profile, $limit);

        $actor = ($query['actor'] ?? null) instanceof User
            ? $query['actor']
            : ($actorId > 0 ? User::query()->find($actorId) : null);
        $effective = $this->resolveScope($actor, $requestedScope);

        $graph = [
            'users' => in_array('users', $profileConfig['domains'], true)
                ? $this->safeBuild(fn (): array => $this->buildUsers($actor, $effective, (int) $profileConfig['node_limit']))
                : [],
            'groups' => in_array('groups', $profileConfig['domains'], true)
                ? $this->safeBuild(fn (): array => $this->buildGroups($actor, $effective, (int) $profileConfig['node_limit']))
                : [],
            'projects' => in_array('projects', $profileConfig['domains'], true)
                ? $this->safeBuild(fn (): array => $this->buildProjects($actor, $effective, (int) $profileConfig['node_limit']))
                : [],
            'tickets' => in_array('tickets', $profileConfig['domains'], true)
                ? $this->safeBuild(fn (): array => $this->buildTickets($actor, $effective, (int) $profileConfig['node_limit']))
                : [],
            'runtime_signals' => in_array('runtime_signals', $profileConfig['domains'], true)
                ? $this->safeBuild(fn (): array => $this->buildRuntimeSignals($effective, (int) $profileConfig['event_limit'], (array) $profileConfig['scope_filters']))
                : [],
        ];

        $result = [
            'trace' => [
                'trace_id' => $traceId,
                'requested_scope' => $requestedScope,
                'effective_scope' => $effective['scope'],
                'scope_reduced_by_rbac' => (bool) $effective['reduced'],
                'query_profile' => (string) $profileConfig['profile'],
                'actor_id' => $actor?->id !== null ? (int) $actor->id : null,
                'generated_at' => now()->toIso8601String(),
                'data_sources' => ['users', 'groups', 'najm_bahar_projects', 'tickets', 'runtime_event_bus'],
            ],
            'nodes' => $graph,
            'edges' => $this->buildEdges($actor, $graph),
            'patterns' => $this->buildDecisionPatterns($graph, (string) $profileConfig['profile']),
        ];

        $this->eventBus->emit('najm_hoda.autonomy.graph_query.executed', [
            'trace_id' => $traceId,
            'actor_id' => $actor?->id !== null ? (int) $actor->id : null,
            'requested_scope' => $requestedScope,
            'effective_scope' => $effective['scope'],
            'scope_reduced_by_rbac' => (bool) $effective['reduced'],
            'query_profile' => (string) $profileConfig['profile'],
            'node_counts' => [
                'users' => count($graph['users']),
                'groups' => count($graph['groups']),
                'projects' => count($graph['projects']),
                'tickets' => count($graph['tickets']),
                'runtime_signals' => count($graph['runtime_signals']),
            ],
            'pattern_counts' => [
                'support_escalation_candidates' => count((array) data_get($result, 'patterns.support_escalation_candidates', [])),
                'project_delivery_risk_hotspots' => count((array) data_get($result, 'patterns.project_delivery_risk_hotspots', [])),
                'ops_alert_chains' => count((array) data_get($result, 'patterns.ops_alert_chains', [])),
            ],
            'scope' => 'autonomy',
            'risk' => 'low',
        ]);

        return $result;
    }

    /**
     * @return array{profile:string,domains:array<int,string>,node_limit:int,event_limit:int,scope_filters:array<int,string>}
     */
    protected function resolveProfile(string $profile, int $limit): array
    {
        $normalized = strtolower(trim($profile));
        $nodeLimit = max(1, $limit);
        $eventLimit = max(1, min(200, $limit * 2));

        return match ($normalized) {
            'member_support' => [
                'profile' => 'member_support',
                'domains' => ['users', 'groups', 'tickets', 'runtime_signals'],
                'node_limit' => min(50, $nodeLimit),
                'event_limit' => min(120, $eventLimit),
                'scope_filters' => ['support', 'auth', 'group'],
            ],
            'project_delivery' => [
                'profile' => 'project_delivery',
                'domains' => ['users', 'groups', 'projects', 'runtime_signals'],
                'node_limit' => min(80, $nodeLimit),
                'event_limit' => min(160, $eventLimit),
                'scope_filters' => ['economy:najm-bahar', 'group'],
            ],
            'ops_triage' => [
                'profile' => 'ops_triage',
                'domains' => ['users', 'groups', 'projects', 'tickets', 'runtime_signals'],
                'node_limit' => min(100, max(20, $nodeLimit)),
                'event_limit' => min(200, max(50, $eventLimit)),
                'scope_filters' => ['ops', 'autonomy', 'support', 'auth', 'content', 'economy:najm-bahar', 'group'],
            ],
            default => [
                'profile' => 'overview',
                'domains' => ['users', 'groups', 'projects', 'tickets', 'runtime_signals'],
                'node_limit' => min(100, $nodeLimit),
                'event_limit' => min(160, $eventLimit),
                'scope_filters' => [],
            ],
        };
    }

    /**
     * @return array{scope:string,reduced:bool}
     */
    protected function resolveScope(?User $actor, string $requestedScope): array
    {
        $requestedScope = $requestedScope === '' ? 'actor' : $requestedScope;
        if ($actor === null) {
            return ['scope' => 'global', 'reduced' => false];
        }

        $isAdmin = (bool) ($actor->is_admin ?? false) || $actor->hasRole('super-admin');
        if ($isAdmin) {
            return ['scope' => $requestedScope, 'reduced' => false];
        }

        if (str_starts_with($requestedScope, 'group:')) {
            $groupId = (int) substr($requestedScope, 6);
            $member = $groupId > 0 && $actor->groups()->where('groups.id', $groupId)->exists();
            if ($member) {
                return ['scope' => "group:{$groupId}", 'reduced' => false];
            }

            return ['scope' => 'actor', 'reduced' => true];
        }

        if ($requestedScope === 'actor') {
            return ['scope' => 'actor', 'reduced' => false];
        }

        return ['scope' => 'actor', 'reduced' => true];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildUsers(?User $actor, array $effective, int $limit): array
    {
        if ($effective['scope'] === 'global') {
            return User::query()->latest('id')->limit($limit)->get(['id', 'first_name', 'last_name', 'email'])->map(
                static fn (User $user): array => [
                    'id' => (int) $user->id,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'email' => (string) ($user->email ?? ''),
                ]
            )->all();
        }

        if ($actor === null) {
            return [];
        }

        return [[
            'id' => (int) $actor->id,
            'name' => trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')),
            'email' => (string) ($actor->email ?? ''),
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildGroups(?User $actor, array $effective, int $limit): array
    {
        $scope = (string) $effective['scope'];

        if ($scope === 'global') {
            return Group::query()->latest('id')->limit($limit)->get(['id', 'name', 'group_type'])->map(
                static fn (Group $group): array => [
                    'id' => (int) $group->id,
                    'name' => (string) ($group->name ?? ''),
                    'group_type' => (string) ($group->group_type ?? ''),
                ]
            )->all();
        }

        if (str_starts_with($scope, 'group:')) {
            $groupId = (int) substr($scope, 6);
            return Group::query()->where('id', $groupId)->limit(1)->get(['id', 'name', 'group_type'])->map(
                static fn (Group $group): array => [
                    'id' => (int) $group->id,
                    'name' => (string) ($group->name ?? ''),
                    'group_type' => (string) ($group->group_type ?? ''),
                ]
            )->all();
        }

        if ($actor === null) {
            return [];
        }

        return $actor->groups()->latest('groups.id')->limit($limit)->get(['groups.id', 'groups.name', 'groups.group_type'])->map(
            static fn (Group $group): array => [
                'id' => (int) $group->id,
                'name' => (string) ($group->name ?? ''),
                'group_type' => (string) ($group->group_type ?? ''),
            ]
        )->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildProjects(?User $actor, array $effective, int $limit): array
    {
        $scope = (string) $effective['scope'];
        $query = NajmBaharProject::query()->latest('id')->limit($limit);

        if ($scope === 'global') {
            return $query->get(['id', 'owner_type', 'owner_id', 'title', 'status'])->map(
                static fn (NajmBaharProject $project): array => [
                    'id' => (int) $project->id,
                    'title' => (string) ($project->title ?? ''),
                    'status' => (string) ($project->status ?? ''),
                    'owner_type' => (string) ($project->owner_type ?? ''),
                    'owner_id' => (int) ($project->owner_id ?? 0),
                ]
            )->all();
        }

        if ($actor === null) {
            return [];
        }

        if (str_starts_with($scope, 'group:')) {
            $groupId = (int) substr($scope, 6);
            $query->where('owner_type', Group::class)->where('owner_id', $groupId);
        } else {
            $groupIds = $actor->groups()->pluck('groups.id')->all();
            $query->where(function ($q) use ($actor, $groupIds): void {
                $q->where(function ($s) use ($actor): void {
                    $s->where('owner_type', User::class)->where('owner_id', $actor->id);
                });
                if (!empty($groupIds)) {
                    $q->orWhere(function ($s) use ($groupIds): void {
                        $s->where('owner_type', Group::class)->whereIn('owner_id', $groupIds);
                    });
                }
            });
        }

        return $query->get(['id', 'owner_type', 'owner_id', 'title', 'status'])->map(
            static fn (NajmBaharProject $project): array => [
                'id' => (int) $project->id,
                'title' => (string) ($project->title ?? ''),
                'status' => (string) ($project->status ?? ''),
                'owner_type' => (string) ($project->owner_type ?? ''),
                'owner_id' => (int) ($project->owner_id ?? 0),
            ]
        )->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildTickets(?User $actor, array $effective, int $limit): array
    {
        $query = Ticket::query()->latest('id')->limit($limit);
        if ((string) $effective['scope'] !== 'global') {
            if ($actor === null) {
                return [];
            }
            $query->where(function ($q) use ($actor): void {
                $q->where('user_id', $actor->id)->orWhere('assignee_id', $actor->id);
            });
        }

        return $query->get(['id', 'user_id', 'assignee_id', 'status', 'priority'])->map(
            static fn (Ticket $ticket): array => [
                'id' => (int) $ticket->id,
                'user_id' => $ticket->user_id !== null ? (int) $ticket->user_id : null,
                'assignee_id' => $ticket->assignee_id !== null ? (int) $ticket->assignee_id : null,
                'status' => (string) ($ticket->status ?? ''),
                'priority' => (string) ($ticket->priority ?? ''),
            ]
        )->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildRuntimeSignals(array $effective, int $limit, array $scopeFilters = []): array
    {
        $events = $this->eventBus->recent(null, $limit * 2);
        $scope = (string) $effective['scope'];

        $filtered = array_values(array_filter($events, static function (array $entry) use ($scope, $scopeFilters): bool {
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
            $eventScope = (string) ($payload['scope'] ?? 'global');
            if (!empty($scopeFilters)) {
                $matched = false;
                foreach ($scopeFilters as $allowedScopePrefix) {
                    $allowedScopePrefix = (string) $allowedScopePrefix;
                    if ($allowedScopePrefix !== '' && str_starts_with($eventScope, $allowedScopePrefix)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return false;
                }
            }
            if ($scope === 'global') {
                return true;
            }
            if (str_starts_with($scope, 'group:')) {
                return $eventScope === 'group' || $eventScope === $scope;
            }
            return str_starts_with($eventScope, 'auth')
                || str_starts_with($eventScope, 'support')
                || str_starts_with($eventScope, 'content')
                || str_starts_with($eventScope, 'economy:najm-bahar')
                || $eventScope === 'group';
        }));

        $filtered = array_slice($filtered, 0, $limit);

        return array_map(static function (array $entry, int $index): array {
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
            $event = (string) ($entry['event'] ?? '');
            $segments = explode('.', $event);
            $outcome = count($segments) > 0 ? (string) end($segments) : '';

            return [
                'id' => "signal:{$index}",
                'event' => $event,
                'timestamp' => (string) ($entry['timestamp'] ?? ''),
                'scope' => (string) ($payload['scope'] ?? 'global'),
                'risk' => (string) ($payload['risk'] ?? 'unknown'),
                'request_id' => (string) ($payload['request_id'] ?? ''),
                'correlation_id' => (string) ($payload['correlation_id'] ?? ''),
                'actor_id' => (string) ($payload['actor_id'] ?? ''),
                'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
                'group_id' => isset($payload['group_id']) ? (int) $payload['group_id'] : null,
                'project_id' => isset($payload['project_id']) ? (int) $payload['project_id'] : null,
                'ticket_id' => isset($payload['ticket_id']) ? (int) $payload['ticket_id'] : null,
                'outcome' => in_array($outcome, ['requested', 'succeeded', 'failed', 'rejected'], true) ? $outcome : 'observed',
            ];
        }, $filtered, array_keys($filtered));
    }

    /**
     * @param array<string, mixed> $graph
     * @return array<int, array<string, mixed>>
     */
    protected function buildEdges(?User $actor, array $graph): array
    {
        $edges = [];
        $actorId = $actor?->id !== null ? (int) $actor->id : null;

        if ($actorId !== null) {
            foreach ($graph['groups'] as $group) {
                $edges[] = [
                    'from' => "user:{$actorId}",
                    'to' => 'group:' . (int) ($group['id'] ?? 0),
                    'type' => 'member_of',
                ];
            }

            foreach ($graph['tickets'] as $ticket) {
                $ticketId = (int) ($ticket['id'] ?? 0);
                if ((int) ($ticket['user_id'] ?? 0) === $actorId) {
                    $edges[] = ['from' => "user:{$actorId}", 'to' => "ticket:{$ticketId}", 'type' => 'opened'];
                }
                if ((int) ($ticket['assignee_id'] ?? 0) === $actorId) {
                    $edges[] = ['from' => "user:{$actorId}", 'to' => "ticket:{$ticketId}", 'type' => 'assigned_to'];
                }
            }
        }

        foreach ($graph['projects'] as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            $ownerType = (string) ($project['owner_type'] ?? '');
            $ownerId = (int) ($project['owner_id'] ?? 0);
            if ($ownerId <= 0) {
                continue;
            }
            if ($ownerType === User::class) {
                $edges[] = ['from' => "user:{$ownerId}", 'to' => "project:{$projectId}", 'type' => 'owns'];
            } elseif ($ownerType === Group::class) {
                $edges[] = ['from' => "group:{$ownerId}", 'to' => "project:{$projectId}", 'type' => 'owns'];
            }
        }

        $firstByCorrelation = [];
        foreach ((array) ($graph['runtime_signals'] ?? []) as $signal) {
            $signalId = (string) ($signal['id'] ?? '');
            if ($signalId === '') {
                continue;
            }

            $signalScope = (string) ($signal['scope'] ?? '');
            $signalOutcome = (string) ($signal['outcome'] ?? 'observed');

            $userId = (int) ($signal['user_id'] ?? 0);
            if ($userId > 0) {
                $edges[] = [
                    'from' => $signalId,
                    'to' => "user:{$userId}",
                    'type' => 'observes_user_context',
                    'semantic' => 'runtime_actor_reference',
                    'confidence' => 0.95,
                    'outcome' => $signalOutcome,
                ];
            }

            $groupId = (int) ($signal['group_id'] ?? 0);
            if ($groupId > 0) {
                $edges[] = [
                    'from' => $signalId,
                    'to' => "group:{$groupId}",
                    'type' => 'affects_group',
                    'semantic' => 'runtime_group_impact',
                    'confidence' => 0.9,
                    'outcome' => $signalOutcome,
                ];
            }

            $projectId = (int) ($signal['project_id'] ?? 0);
            if ($projectId > 0) {
                $edges[] = [
                    'from' => $signalId,
                    'to' => "project:{$projectId}",
                    'type' => 'affects_project',
                    'semantic' => 'runtime_project_impact',
                    'confidence' => 0.9,
                    'outcome' => $signalOutcome,
                ];
            }

            $ticketId = (int) ($signal['ticket_id'] ?? 0);
            if ($ticketId > 0) {
                $edges[] = [
                    'from' => $signalId,
                    'to' => "ticket:{$ticketId}",
                    'type' => 'affects_ticket',
                    'semantic' => 'runtime_ticket_impact',
                    'confidence' => 0.9,
                    'outcome' => $signalOutcome,
                ];
            }

            if ($signalScope === 'ops' || str_starts_with($signalScope, 'autonomy')) {
                $edges[] = [
                    'from' => $signalId,
                    'to' => 'domain:operations',
                    'type' => 'signals_operational_state',
                    'semantic' => 'ops_triage',
                    'confidence' => 0.85,
                    'outcome' => $signalOutcome,
                ];
            }

            $correlationId = trim((string) ($signal['correlation_id'] ?? ''));
            if ($correlationId !== '') {
                if (!array_key_exists($correlationId, $firstByCorrelation)) {
                    $firstByCorrelation[$correlationId] = $signalId;
                } elseif ($firstByCorrelation[$correlationId] !== $signalId) {
                    $edges[] = [
                        'from' => $signalId,
                        'to' => (string) $firstByCorrelation[$correlationId],
                        'type' => 'correlates_with',
                        'semantic' => 'shared_correlation_id',
                        'confidence' => 0.8,
                        'outcome' => $signalOutcome,
                    ];
                }
            }
        }

        return $edges;
    }

    /**
     * @param array<string, mixed> $graph
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function buildDecisionPatterns(array $graph, string $profile): array
    {
        $signals = (array) ($graph['runtime_signals'] ?? []);
        $patterns = [
            'support_escalation_candidates' => $this->buildSupportEscalationCandidates($signals),
            'project_delivery_risk_hotspots' => $this->buildProjectRiskHotspots($signals),
            'ops_alert_chains' => $this->buildOpsAlertChains($signals),
        ];

        if ($profile === 'member_support') {
            $patterns['project_delivery_risk_hotspots'] = [];
        } elseif ($profile === 'project_delivery') {
            $patterns['support_escalation_candidates'] = [];
        }

        return $patterns;
    }

    /**
     * @param array<int, array<string, mixed>> $signals
     * @return array<int, array<string, mixed>>
     */
    protected function buildSupportEscalationCandidates(array $signals): array
    {
        $bucket = [];
        foreach ($signals as $signal) {
            $scope = (string) ($signal['scope'] ?? '');
            $outcome = (string) ($signal['outcome'] ?? 'observed');
            $ticketId = (int) ($signal['ticket_id'] ?? 0);
            if (!str_starts_with($scope, 'support') || $ticketId <= 0) {
                continue;
            }
            if (!in_array($outcome, ['failed', 'rejected'], true)) {
                continue;
            }

            $key = "ticket:{$ticketId}";
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'ticket_id' => $ticketId,
                    'risk_level' => (string) ($signal['risk'] ?? 'unknown'),
                    'failure_events' => [],
                    'correlation_ids' => [],
                    'action' => 'escalate_to_human_triage',
                ];
            }

            $bucket[$key]['failure_events'][] = (string) ($signal['event'] ?? '');
            $corr = trim((string) ($signal['correlation_id'] ?? ''));
            if ($corr !== '' && !in_array($corr, $bucket[$key]['correlation_ids'], true)) {
                $bucket[$key]['correlation_ids'][] = $corr;
            }
            if (((string) ($signal['risk'] ?? 'unknown')) === 'high') {
                $bucket[$key]['risk_level'] = 'high';
            }
        }

        return array_values($bucket);
    }

    /**
     * @param array<int, array<string, mixed>> $signals
     * @return array<int, array<string, mixed>>
     */
    protected function buildProjectRiskHotspots(array $signals): array
    {
        $bucket = [];
        foreach ($signals as $signal) {
            $scope = (string) ($signal['scope'] ?? '');
            $outcome = (string) ($signal['outcome'] ?? 'observed');
            $projectId = (int) ($signal['project_id'] ?? 0);
            if ($projectId <= 0 || !str_starts_with($scope, 'economy:najm-bahar')) {
                continue;
            }
            if (!in_array($outcome, ['failed', 'rejected'], true)) {
                continue;
            }

            $key = "project:{$projectId}";
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'project_id' => $projectId,
                    'failure_count' => 0,
                    'latest_event' => '',
                    'recommended_action' => 'review_delivery_dependencies',
                ];
            }
            $bucket[$key]['failure_count']++;
            $bucket[$key]['latest_event'] = (string) ($signal['event'] ?? '');
        }

        return array_values($bucket);
    }

    /**
     * @param array<int, array<string, mixed>> $signals
     * @return array<int, array<string, mixed>>
     */
    protected function buildOpsAlertChains(array $signals): array
    {
        $bucket = [];
        foreach ($signals as $signal) {
            $scope = (string) ($signal['scope'] ?? '');
            if ($scope !== 'ops' && !str_starts_with($scope, 'autonomy')) {
                continue;
            }

            $corr = trim((string) ($signal['correlation_id'] ?? ''));
            $key = $corr !== '' ? $corr : (string) ($signal['id'] ?? '');
            if ($key === '') {
                continue;
            }

            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'correlation_id' => $corr,
                    'events' => [],
                    'risk_level' => (string) ($signal['risk'] ?? 'unknown'),
                    'action' => 'open_ops_incident',
                ];
            }

            $bucket[$key]['events'][] = (string) ($signal['event'] ?? '');
            if (((string) ($signal['risk'] ?? 'unknown')) === 'high') {
                $bucket[$key]['risk_level'] = 'high';
            }
        }

        return array_values(array_filter($bucket, static fn (array $row): bool => count((array) ($row['events'] ?? [])) > 0));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function safeBuild(callable $resolver): array
    {
        try {
            $value = $resolver();
            return is_array($value) ? $value : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
