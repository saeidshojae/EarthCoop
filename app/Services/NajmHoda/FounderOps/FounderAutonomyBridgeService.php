<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;

/**
 * Bridge Founder Operations into Najm Hoda's autonomous-governance loop.
 *
 * This service deliberately does not execute mutations. It converts high-value
 * Founder attention items into the next policy-governed action preparation step.
 * Execution remains behind FounderActionExecutionService and domain command layers.
 */
class FounderAutonomyBridgeService
{
    public function __construct(
        protected FounderAttentionService $attention,
        protected FounderActionRequestService $requests,
        protected RuntimeEventBus $events
    ) {}

    /** @return array<string,mixed> */
    public function plan(int $hours = 24, int $limit = 12): array
    {
        $brief = $this->attention->brief($hours);
        $items = array_slice((array) ($brief['items'] ?? []), 0, max(1, min($limit, 50)));
        $prepared = [];

        foreach ($items as $item) {
            if (! is_array($item)) continue;

            $mapped = $this->mapAttentionToAction($item);
            if ($mapped === null) continue;

            [$domain, $action] = $mapped;
            $result = $this->requests->prepare($domain, $action, [
                'attention_priority' => (string) ($item['priority'] ?? ''),
                'reason_code' => $this->reasonCode($item),
                'source_event' => 'founder_attention',
            ]);

            $prepared[] = [
                'source_attention' => [
                    'priority' => $item['priority'] ?? null,
                    'domain' => $item['domain'] ?? null,
                    'title' => $item['title'] ?? null,
                ],
                'domain' => $domain,
                'action' => $action,
                'preparation' => $result,
            ];
        }

        $summary = [
            'total' => count($prepared),
            'awaiting_approval' => $this->countStatus($prepared, 'awaiting_approval'),
            'delegated_ready' => $this->countStatus($prepared, 'delegated_ready'),
            'delegation_required' => $this->countStatus($prepared, 'delegation_required'),
            'proposal_only' => $this->countStatus($prepared, 'proposal_only'),
            'read_only' => $this->countStatus($prepared, 'read_only'),
            'blocked' => $this->countStatus($prepared, 'blocked'),
        ];

        $this->events->emit('najm_hoda.founder_ops.autonomy.plan_prepared', [
            'count' => $summary['total'],
            'awaiting_approval' => $summary['awaiting_approval'],
            'delegated_ready' => $summary['delegated_ready'],
            'blocked' => $summary['blocked'],
        ]);

        return [
            'generated_at' => now()->toIso8601String(),
            'attention_summary' => $brief['summary'] ?? [],
            'summary' => $summary,
            'actions' => $prepared,
        ];
    }

    /** @param array<string,mixed> $item @return array{0:string,1:string}|null */
    protected function mapAttentionToAction(array $item): ?array
    {
        $domain = (string) ($item['domain'] ?? '');
        $title = mb_strtolower((string) ($item['title'] ?? ''));

        return match ($domain) {
            'support' => str_contains($title, 'no assignee')
                ? ['support', 'assign_priority']
                : ['support', 'draft_reply'],
            'governance' => ['governance', 'flag_anomaly'],
            'reports_moderation' => ['reports_moderation', 'prepare_case_summary'],
            'stock' => ['stock', 'flag_settlement_issue'],
            'secretariat' => ['secretariat', 'prepare_follow_up'],
            'najm_bahar' => ['najm_bahar', 'flag_transaction_anomaly'],
            'content' => ['content', 'draft_faq_answer'],
            'approvals' => ['reference_data', 'recommend_approval'],
            'invitations' => ['invitations', 'recommend_request_decision'],
            'admin_settings' => ['admin_settings', 'recommend_change'],
            'runtime_health' => ['runtime_health', 'run_read_only_diagnostic'],
            'users' => ['users', 'draft_support_response'],
            'groups' => ['groups', 'propose_action_item'],
            'notifications' => ['notifications', 'draft_announcement'],
            'blog' => ['blog', 'suggest_edit'],
            default => null,
        };
    }

    /** @param array<string,mixed> $item */
    protected function reasonCode(array $item): string
    {
        return substr(hash('sha256', implode('|', [
            (string) ($item['priority'] ?? ''),
            (string) ($item['domain'] ?? ''),
            (string) ($item['title'] ?? ''),
        ])), 0, 20);
    }

    /** @param array<int,array<string,mixed>> $prepared */
    protected function countStatus(array $prepared, string $status): int
    {
        return count(array_filter($prepared, static fn (array $item): bool =>
            (string) data_get($item, 'preparation.status', '') === $status
        ));
    }
}
