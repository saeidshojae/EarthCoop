<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class FounderAutonomyBridgeService
{
    public function __construct(
        protected FounderAttentionService $attention,
        protected FounderActionRequestService $requests,
        protected FounderSupportCandidateService $supportCandidates,
        protected RuntimeEventBus $events
    ) {}

    /** @return array<string,mixed> */
    public function plan(int $hours = 24, int $limit = 12): array
    {
        $brief = $this->attention->brief($hours);
        $items = array_slice((array) ($brief['items'] ?? []), 0, max(1, min($limit, 50)));
        $prepared = [];

        foreach ($this->supportCandidates->candidates(min(5, $limit)) as $candidate) {
            $ticketId = (int) ($candidate['ticket_id'] ?? 0);
            if ($ticketId <= 0) continue;

            foreach (['classify_ticket', 'assign_priority'] as $action) {
                $context = [
                    'entity_type' => 'ticket',
                    'entity_id' => $ticketId,
                    'attention_priority' => ($candidate['priority'] ?? null) === 'high' ? 'P1' : 'P2',
                    'reason_code' => substr(hash('sha256', 'support|' . $action . '|' . $ticketId), 0, 20),
                    'source_event' => 'founder_support_candidate',
                ];
                $prepared[] = [
                    'source_attention' => ['priority' => $context['attention_priority'], 'domain' => 'support', 'title' => 'Support ticket requires operational triage'],
                    'domain' => 'support', 'action' => $action, 'action_context' => $context,
                    'preparation' => $this->requests->prepare('support', $action, $context),
                ];
            }
        }

        foreach ($items as $item) {
            if (! is_array($item) || (string) ($item['domain'] ?? '') === 'support') continue;
            $mapped = $this->mapAttentionToAction($item);
            if ($mapped === null) continue;

            [$domain, $action] = $mapped;
            $context = [
                'attention_priority' => (string) ($item['priority'] ?? ''),
                'reason_code' => $this->reasonCode($item),
                'source_event' => 'founder_attention',
            ];
            $entityId = data_get($item, 'context.entity_id');
            if (is_numeric($entityId)) {
                $context['entity_id'] = (int) $entityId;
                $context['entity_type'] = (string) data_get($item, 'context.entity_type', $domain);
            }

            $prepared[] = [
                'source_attention' => ['priority' => $item['priority'] ?? null, 'domain' => $item['domain'] ?? null, 'title' => $item['title'] ?? null],
                'domain' => $domain, 'action' => $action, 'action_context' => $context,
                'preparation' => $this->requests->prepare($domain, $action, $context),
            ];
        }

        $prepared = array_slice($prepared, 0, max(1, min($limit, 50)));
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
            'count' => $summary['total'], 'awaiting_approval' => $summary['awaiting_approval'],
            'delegated_ready' => $summary['delegated_ready'], 'blocked' => $summary['blocked'],
        ]);

        return ['generated_at' => now()->toIso8601String(), 'attention_summary' => $brief['summary'] ?? [], 'summary' => $summary, 'actions' => $prepared];
    }

    protected function mapAttentionToAction(array $item): ?array
    {
        return match ((string) ($item['domain'] ?? '')) {
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

    protected function reasonCode(array $item): string
    {
        return substr(hash('sha256', implode('|', [(string) ($item['priority'] ?? ''), (string) ($item['domain'] ?? ''), (string) ($item['title'] ?? '')])), 0, 20);
    }

    protected function countStatus(array $prepared, string $status): int
    {
        return count(array_filter($prepared, static fn (array $item): bool => (string) data_get($item, 'preparation.status', '') === $status));
    }
}
