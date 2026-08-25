<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderMinistryAgencyService
{
    private const INTENT_DOMAINS = [
        'users_registration' => ['users', 'invitations'],
        'reference_data' => ['reference_data', 'locations'],
        'support_moderation' => ['support', 'reports_moderation'],
        'groups' => ['groups'],
        'governance' => ['governance'],
        'najm_bahar' => ['najm_bahar'],
        'stock' => ['stock'],
        'secretariat' => ['secretariat'],
        'communications' => ['email', 'blog', 'content', 'notifications', 'support'],
        'system_health' => ['runtime_health'],
    ];

    private const WORK_DRIVEN_INTENTS = [
        'morning_brief', 'end_of_day', 'urgent_items', 'pending_approvals',
    ];

    public function __construct(
        protected FounderActionAuthorityService $authority,
        protected FounderDelegationGrantService $delegations,
        protected FounderExecutiveConnectivityService $connectivity,
    ) {}

    /** @return array<string,mixed> */
    public function describe(string $intent, array $items): array
    {
        $connectivity = $this->connectivity->report();
        $domains = (array) ($connectivity['domains'] ?? []);
        $domainKeys = $this->domainKeys($intent, $items, $domains);
        $activeDelegations = $this->activeDelegations($domainKeys);
        $grantIndex = [];
        foreach ($activeDelegations as $grant) {
            $grantIndex[(string) ($grant['domain'] ?? '').'.'.(string) ($grant['action'] ?? '')] = true;
        }

        $mayDoNow = [];
        $mayPrepare = [];
        $blocked = [];

        foreach ($domainKeys as $domainKey) {
            $domain = (array) ($domains[$domainKey] ?? []);
            foreach ((array) ($domain['actions'] ?? []) as $action => $evidence) {
                if (! is_string($action) || ! is_array($evidence)) continue;

                $mode = (string) ($evidence['mode'] ?? $this->authority->mode($domainKey, $action));
                $state = (string) ($evidence['state'] ?? 'missing');
                $base = [
                    'domain' => $domainKey,
                    'action' => $action,
                    'mode' => $mode,
                    'state' => $state,
                ];

                if ($state === 'connected' && $mode === 'delegated_safe') {
                    $key = $domainKey.'.'.$action;
                    $base['delegation_active'] = isset($grantIndex[$key]);
                    $mayPrepare[] = $base;
                    if (isset($grantIndex[$key])) $mayDoNow[] = $base;
                    continue;
                }

                if ($state === 'connected' && $mode === 'propose') {
                    $mayPrepare[] = $base;
                    continue;
                }

                if (in_array($state, ['missing', 'blocked_dependency', 'protected'], true)) {
                    $blocked[] = $base + [
                        'reason' => $this->blockedReason($state, $evidence),
                        'dependency' => $state === 'blocked_dependency'
                            ? (string) data_get($evidence, 'block.dependency', '')
                            : null,
                    ];
                }
            }
        }

        $needsFounderDecision = [];
        foreach ($items as $raw) {
            if (! is_array($raw) || ($raw['kind'] ?? null) !== 'approval') continue;
            $domain = (string) ($raw['domain'] ?? '');
            $action = (string) ($raw['action'] ?? '');
            if ($domain === '' || $action === '' || ! in_array($domain, $domainKeys, true)) continue;
            $evidence = (array) data_get($domains, $domain.'.actions.'.$action, []);
            if (($evidence['state'] ?? null) !== 'connected' || ($evidence['mode'] ?? null) !== 'approval_required') continue;

            $needsFounderDecision[] = [
                'domain' => $domain,
                'action' => $action,
                'mode' => 'approval_required',
                'state' => 'connected',
                'approval_request_id' => $raw['approval_request_id'] ?? null,
                'entity_type' => $raw['entity_type'] ?? null,
                'entity_id' => $raw['entity_id'] ?? null,
                'title' => $raw['title'] ?? null,
            ];
        }

        $connected = $domainKeys !== [];
        foreach ($domainKeys as $domainKey) {
            if (! (bool) data_get($domains, $domainKey.'.read_connected', false)) {
                $connected = false;
                break;
            }
        }

        return [
            'scope' => in_array($intent, self::WORK_DRIVEN_INTENTS, true) ? 'global' : 'intent',
            'domain_keys' => $domainKeys,
            'connected' => $connected,
            'active_delegations' => $activeDelegations,
            'may_do_now' => $mayDoNow,
            'may_prepare' => $mayPrepare,
            'needs_founder_decision' => $needsFounderDecision,
            'blocked' => $blocked,
            'summary' => $this->summary($domainKeys, $mayDoNow, $mayPrepare, $needsFounderDecision, $blocked),
        ];
    }

    /** @return array<int,string> */
    protected function domainKeys(string $intent, array $items, array $domains): array
    {
        if ($intent === 'authority') return array_values(array_keys($domains));
        if (isset(self::INTENT_DOMAINS[$intent])) return self::INTENT_DOMAINS[$intent];
        if (! in_array($intent, self::WORK_DRIVEN_INTENTS, true)) return [];

        $keys = [];
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $domain = (string) ($item['domain'] ?? '');
            if ($domain !== '' && isset($domains[$domain]) && ! in_array($domain, $keys, true)) $keys[] = $domain;
        }
        return $keys;
    }

    /** @return array<int,array<string,mixed>> */
    protected function activeDelegations(array $domainKeys): array
    {
        $result = [];
        foreach ($this->delegations->active() as $grant) {
            if (! is_array($grant) || ! in_array((string) ($grant['domain'] ?? ''), $domainKeys, true)) continue;
            $result[] = [
                'id' => $grant['id'] ?? null,
                'domain' => $grant['domain'] ?? null,
                'action' => $grant['action'] ?? null,
                'expires_at' => $grant['expires_at'] ?? null,
            ];
        }
        return $result;
    }

    protected function blockedReason(string $state, array $evidence): string
    {
        return match ($state) {
            'blocked_dependency' => (string) data_get($evidence, 'block.reason', 'blocked_dependency'),
            'protected' => 'forbidden_by_policy',
            default => 'canonical_connectivity_missing',
        };
    }

    protected function summary(array $domains, array $mayDoNow, array $mayPrepare, array $needsFounderDecision, array $blocked): string
    {
        if ($domains === []) return 'در این گزارش حوزه اجرایی فعالی کارت نشده است.';

        return sprintf(
            'توان اجرایی این گزارش: %d اقدام با واگذاری فعال قابل اجراست، %d قابلیت برای آماده‌سازی یا تحلیل متصل است، %d تصمیم واقعی منتظر شماست و %d قابلیت مرتبط فعلاً مسدود است.',
            count($mayDoNow),
            count($mayPrepare),
            count($needsFounderDecision),
            count($blocked),
        );
    }
}
