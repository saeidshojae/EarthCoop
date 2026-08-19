<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FounderDelegationGrantService
{
    protected string $cacheKey = 'najm_hoda:founder_ops:delegation_grants';

    public function __construct(
        protected FounderActionAuthorityService $authority,
        protected RuntimeEventBus $events
    ) {}

    /** @return array<string,mixed> */
    public function grant(string $domain, string $action, int $founderUserId, int $hours = 24): array
    {
        if (! $this->isAuthorizedFounder($founderUserId)) {
            return ['success' => false, 'reason' => 'founder_identity_not_authorized'];
        }

        $mode = $this->authority->mode($domain, $action);
        if ($mode !== 'delegated_safe') {
            return ['success' => false, 'reason' => 'action_not_delegatable', 'mode' => $mode];
        }

        $hours = max(1, min($hours, 24 * 30));
        $now = now();
        $grant = [
            'id' => (string) Str::uuid(),
            'domain' => $domain,
            'action' => $action,
            'granted_by' => $founderUserId,
            'granted_at' => $now->toIso8601String(),
            'expires_at' => $now->copy()->addHours($hours)->toIso8601String(),
            'revoked_at' => null,
            'revoked_by' => null,
        ];

        $grants = $this->load();
        foreach ($grants as &$existing) {
            if (($existing['domain'] ?? null) === $domain && ($existing['action'] ?? null) === $action && $this->isActiveGrant($existing)) {
                $existing['revoked_at'] = $now->toIso8601String();
                $existing['revoked_by'] = $founderUserId;
            }
        }
        unset($existing);

        array_unshift($grants, $grant);
        $this->store(array_slice($grants, 0, 500));

        $this->events->emit('najm_hoda.founder.delegation.granted', [
            'grant_id' => $grant['id'], 'domain' => $domain, 'action' => $action,
            'granted_by' => $founderUserId, 'expires_at' => $grant['expires_at'],
        ]);

        return ['success' => true, 'grant' => $grant];
    }

    /** @return array<string,mixed> */
    public function revoke(string $grantId, int $founderUserId): array
    {
        if (! $this->isAuthorizedFounder($founderUserId)) {
            return ['success' => false, 'reason' => 'founder_identity_not_authorized'];
        }

        $grants = $this->load();
        foreach ($grants as $index => $grant) {
            if ((string) ($grant['id'] ?? '') !== $grantId) continue;
            if (! $this->isActiveGrant($grant)) return ['success' => false, 'reason' => 'grant_not_active'];

            $grant['revoked_at'] = now()->toIso8601String();
            $grant['revoked_by'] = $founderUserId;
            $grants[$index] = $grant;
            $this->store($grants);

            $this->events->emit('najm_hoda.founder.delegation.revoked', [
                'grant_id' => $grantId, 'domain' => $grant['domain'] ?? null,
                'action' => $grant['action'] ?? null, 'revoked_by' => $founderUserId,
            ]);
            return ['success' => true, 'grant' => $grant];
        }
        return ['success' => false, 'reason' => 'grant_not_found'];
    }

    public function isGranted(string $domain, string $action): bool
    {
        foreach ($this->load() as $grant) {
            if (($grant['domain'] ?? null) === $domain && ($grant['action'] ?? null) === $action && $this->isActiveGrant($grant)) return true;
        }
        return false;
    }

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return array_values(array_filter($this->load(), fn (array $grant): bool => $this->isActiveGrant($grant)));
    }

    protected function isAuthorizedFounder(int $userId): bool
    {
        $ids = array_map('intval', (array) config('najm-hoda-founder-action-policy.founder_approval.user_ids', []));
        return $userId > 0 && in_array($userId, $ids, true);
    }

    protected function isActiveGrant(array $grant): bool
    {
        if (! empty($grant['revoked_at'])) return false;
        $expiresAt = $grant['expires_at'] ?? null;
        if (! is_string($expiresAt) || $expiresAt === '') return false;
        try { return now()->lt(\Carbon\CarbonImmutable::parse($expiresAt)); }
        catch (\Throwable) { return false; }
    }

    protected function load(): array
    {
        $grants = Cache::get($this->cacheKey, []);
        return is_array($grants) ? $grants : [];
    }

    protected function store(array $grants): void
    {
        Cache::put($this->cacheKey, $grants, now()->addDays(45));
    }
}
