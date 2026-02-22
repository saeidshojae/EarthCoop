<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NajmHodaDelegatedPermissionService
{
    protected string $delegationsKey = 'najm_hoda:autonomy:delegations';

    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    public function grant(array $spec): array
    {
        $principalType = strtolower(trim((string) ($spec['principal_type'] ?? 'user')));
        $principalId = (string) ($spec['principal_id'] ?? '');
        $action = trim((string) ($spec['action'] ?? ''));
        $scope = trim((string) ($spec['scope'] ?? 'global'));
        $createdBy = isset($spec['created_by']) ? (int) $spec['created_by'] : null;
        $requireApproval = (bool) ($spec['require_approval'] ?? false);

        if (!in_array($principalType, ['user', 'role', 'group'], true)) {
            return ['success' => false, 'reason' => 'invalid_principal_type'];
        }
        if ($principalId === '' || $action === '') {
            return ['success' => false, 'reason' => 'missing_required_fields'];
        }

        $expiresAt = $this->normalizeExpiry($spec);
        if ($expiresAt === null) {
            return ['success' => false, 'reason' => 'invalid_expiry'];
        }

        $delegation = [
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'principal_type' => $principalType,
            'principal_id' => $principalId,
            'action' => $action,
            'scope' => $scope === '' ? 'global' : $scope,
            'require_approval' => $requireApproval,
            'created_by' => $createdBy,
            'created_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'revoked_by' => null,
            'reason' => isset($spec['reason']) ? trim((string) $spec['reason']) : null,
        ];

        $all = $this->all();
        array_unshift($all, $delegation);
        $maxHistory = max(50, (int) config('najm-hoda.runtime.autonomy.permissioning_v2.max_delegation_history', 2000));
        $all = array_slice($all, 0, $maxHistory);
        $this->store($all);

        $this->eventBus->emit('najm_hoda.autonomy.delegation.granted', [
            'delegation_id' => $delegation['id'],
            'principal_type' => $principalType,
            'principal_id' => $principalId,
            'action' => $action,
            'scope' => $delegation['scope'],
            'require_approval' => $requireApproval,
            'expires_at' => $expiresAt,
        ]);

        return ['success' => true, 'delegation' => $delegation];
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(string $delegationId, ?int $revokedBy = null, ?string $reason = null): array
    {
        $delegationId = trim($delegationId);
        if ($delegationId === '') {
            return ['success' => false, 'reason' => 'invalid_delegation_id'];
        }

        $all = $this->all();
        foreach ($all as $index => $item) {
            if ((string) ($item['id'] ?? '') !== $delegationId) {
                continue;
            }

            if ((string) ($item['status'] ?? '') !== 'active') {
                return ['success' => false, 'reason' => 'not_active'];
            }

            $item['status'] = 'revoked';
            $item['revoked_at'] = now()->toIso8601String();
            $item['revoked_by'] = $revokedBy;
            $item['reason'] = $reason !== null ? trim($reason) : ($item['reason'] ?? null);
            $all[$index] = $item;
            $this->store($all);

            $this->eventBus->emit('najm_hoda.autonomy.delegation.revoked', [
                'delegation_id' => $delegationId,
                'revoked_by' => $revokedBy,
                'reason' => $item['reason'],
            ]);

            return ['success' => true, 'delegation' => $item];
        }

        return ['success' => false, 'reason' => 'delegation_not_found'];
    }

    /**
     * @return array<string, mixed>
     */
    public function authorize(?int $actorId, string $action, string $scope = 'global', array $context = []): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.permissioning_v2.enabled', true)) {
            return ['allowed' => true, 'reason' => 'permissioning_disabled'];
        }

        if ($actorId === null || $actorId <= 0) {
            return ['allowed' => false, 'reason' => 'actor_required'];
        }

        $this->expireDelegations();
        $scope = trim($scope) === '' ? 'global' : trim($scope);
        $delegations = $this->activeDelegationsFor($actorId, $action, $scope, $context);
        if (empty($delegations)) {
            $this->eventBus->emit('najm_hoda.autonomy.delegation.denied', [
                'actor_id' => $actorId,
                'action' => $action,
                'scope' => $scope,
                'reason' => 'no_active_delegation',
            ]);
            return ['allowed' => false, 'reason' => 'no_active_delegation'];
        }

        $delegation = $delegations[0];
        if ((bool) ($delegation['require_approval'] ?? false) && (string) ($context['approval_state'] ?? '') !== 'approved') {
            return [
                'allowed' => false,
                'reason' => 'delegation_requires_approval',
                'delegation_id' => (string) ($delegation['id'] ?? ''),
            ];
        }

        $this->eventBus->emit('najm_hoda.autonomy.delegation.authorized', [
            'actor_id' => $actorId,
            'action' => $action,
            'scope' => $scope,
            'delegation_id' => (string) ($delegation['id'] ?? ''),
        ]);

        return [
            'allowed' => true,
            'reason' => 'delegation_match',
            'delegation_id' => (string) ($delegation['id'] ?? ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActive(?int $actorId = null, ?string $action = null): array
    {
        $this->expireDelegations();
        $all = array_values(array_filter($this->all(), static function (array $item): bool {
            return (string) ($item['status'] ?? '') === 'active';
        }));

        if ($actorId !== null && $actorId > 0) {
            $all = array_values(array_filter($all, function (array $item) use ($actorId): bool {
                return $this->matchesPrincipal($actorId, $item);
            }));
        }

        if ($action !== null && trim($action) !== '') {
            $needle = trim($action);
            $all = array_values(array_filter($all, static fn (array $item): bool => (string) ($item['action'] ?? '') === $needle));
        }

        return $all;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function activeDelegationsFor(int $actorId, string $action, string $scope, array $context = []): array
    {
        $result = [];
        foreach ($this->all() as $item) {
            if ((string) ($item['status'] ?? '') !== 'active') {
                continue;
            }
            if ((string) ($item['action'] ?? '') !== $action) {
                continue;
            }

            $delegationScope = (string) ($item['scope'] ?? 'global');
            if ($delegationScope !== 'global' && $delegationScope !== $scope) {
                continue;
            }

            if ($this->matchesPrincipal($actorId, $item, $context)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $delegation
     */
    protected function matchesPrincipal(int $actorId, array $delegation, array $context = []): bool
    {
        $type = (string) ($delegation['principal_type'] ?? '');
        $id = (string) ($delegation['principal_id'] ?? '');

        if ($type === 'user') {
            return (string) $actorId === $id;
        }

        if ($type === 'role') {
            if ($this->matchesRoleContext($id, $context)) {
                return true;
            }

            try {
                $user = User::query()->find($actorId);
                if ($user === null) {
                    return false;
                }

                if (is_numeric($id) && $user->roles()->where('id', (int) $id)->exists()) {
                    return true;
                }

                return $user->roles()->where('slug', $id)->exists() || $user->roles()->where('name', $id)->exists();
            } catch (\Throwable) {
                return false;
            }
        }

        if ($type === 'group') {
            if ($this->matchesGroupContext($id, $context)) {
                return true;
            }

            try {
                $user = User::query()->find($actorId);
                if ($user === null) {
                    return false;
                }

                if (!is_numeric($id)) {
                    return false;
                }

                return $user->groups()->where('groups.id', (int) $id)->exists();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function expireDelegations(): void
    {
        $changed = false;
        $all = $this->all();
        foreach ($all as $i => $item) {
            if ((string) ($item['status'] ?? '') !== 'active') {
                continue;
            }

            $expiresAt = (string) ($item['expires_at'] ?? '');
            if ($expiresAt === '') {
                continue;
            }

            try {
                if (now()->greaterThan(\Carbon\CarbonImmutable::parse($expiresAt))) {
                    $item['status'] = 'expired';
                    $all[$i] = $item;
                    $changed = true;
                    $this->eventBus->emit('najm_hoda.autonomy.delegation.expired', [
                        'delegation_id' => (string) ($item['id'] ?? ''),
                    ]);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($changed) {
            $this->store($all);
        }
    }

    /**
     * @param array<string, mixed> $spec
     */
    protected function normalizeExpiry(array $spec): ?string
    {
        if (isset($spec['expires_at']) && trim((string) $spec['expires_at']) !== '') {
            try {
                return \Carbon\CarbonImmutable::parse((string) $spec['expires_at'])->toIso8601String();
            } catch (\Throwable) {
                return null;
            }
        }

        $minutes = isset($spec['expires_in_minutes']) ? (int) $spec['expires_in_minutes'] : (int) config('najm-hoda.runtime.autonomy.permissioning_v2.default_expiry_minutes', 1440);
        $minutes = max(1, $minutes);
        return now()->addMinutes($minutes)->toIso8601String();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function all(): array
    {
        $data = Cache::get($this->delegationsKey, []);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    protected function store(array $data): void
    {
        $ttl = max(60, (int) config('najm-hoda.runtime.autonomy.permissioning_v2.retention_minutes', 10080));
        Cache::put($this->delegationsKey, $data, now()->addMinutes($ttl));
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function matchesRoleContext(string $roleReference, array $context): bool
    {
        if ($roleReference === '') {
            return false;
        }

        $normalizedRef = mb_strtolower(trim($roleReference));
        $candidates = $this->extractContextValues($context, [
            'role',
            'roles',
            'role_id',
            'role_ids',
            'role_slug',
            'role_slugs',
            'actor_roles',
        ]);

        foreach ($candidates as $candidate) {
            if (mb_strtolower(trim($candidate)) === $normalizedRef) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function matchesGroupContext(string $groupReference, array $context): bool
    {
        if ($groupReference === '') {
            return false;
        }

        $normalizedRef = trim($groupReference);
        $candidates = $this->extractContextValues($context, [
            'group_id',
            'group_ids',
            'target_group_id',
            'target_group_ids',
            'scope_group_id',
            'scope_group_ids',
        ]);

        foreach ($candidates as $candidate) {
            if (trim($candidate) === $normalizedRef) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    protected function extractContextValues(array $context, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $raw = $context[$key];
            if (is_scalar($raw) && trim((string) $raw) !== '') {
                $values[] = (string) $raw;
                continue;
            }

            if (!is_array($raw)) {
                continue;
            }

            foreach ($raw as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $item = trim((string) $item);
                if ($item !== '') {
                    $values[] = $item;
                }
            }
        }

        return array_values(array_unique($values));
    }
}
