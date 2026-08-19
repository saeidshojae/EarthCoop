<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Ticket;
use App\Services\Moderation\ModerationCaseSummaryService;
use App\Services\NajmHoda\Runtime\NajmHodaOpsHealthMonitor;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Services\Support\TicketManagementService;
use App\Services\Support\TicketReplyDraftService;

class FounderLowRiskDomainActionService
{
    public function __construct(
        protected NajmHodaOpsHealthMonitor $health,
        protected TicketManagementService $tickets,
        protected TicketReplyDraftService $replyDrafts,
        protected ModerationCaseSummaryService $moderationCases,
        protected RuntimeEventBus $events
    ) {}

    public function supports(string $domain, string $action): bool
    {
        return in_array($domain . '.' . $action, [
            'runtime_health.collect_health_snapshot',
            'runtime_health.run_read_only_diagnostic',
            'support.classify_ticket',
            'support.assign_priority',
            'support.draft_reply',
            'reports_moderation.prepare_case_summary',
            'reports_moderation.classify_report',
        ], true);
    }

    public function execute(string $domain, string $action, array $context = []): array
    {
        if (! $this->supports($domain, $action)) {
            return ['success' => false, 'status' => 'unsupported', 'reason' => 'no_canonical_low_risk_handler'];
        }

        $reasonCode = is_scalar($context['reason_code'] ?? null) ? (string) $context['reason_code'] : null;

        if ($domain === 'support') {
            $ticketId = (int) ($context['entity_id'] ?? 0);
            $ticket = $ticketId > 0 ? Ticket::query()->find($ticketId) : null;
            if (! $ticket) return ['success' => false, 'status' => 'not_found', 'reason' => 'ticket_not_found'];
            if (! in_array((string) $ticket->status, ['open', 'in-progress'], true)) {
                return ['success' => false, 'status' => 'skipped', 'reason' => 'ticket_not_active'];
            }

            $result = match ($action) {
                'classify_ticket' => $this->tickets->classify($ticket),
                'assign_priority' => $this->tickets->assignPriority($ticket),
                'draft_reply' => $this->replyDrafts->generate($ticket, $reasonCode),
                default => ['success' => false, 'status' => 'unsupported'],
            };

            $this->events->emit('najm_hoda.founder_ops.low_risk.completed', [
                'domain'=>$domain,'action'=>$action,'entity_type'=>'ticket','entity_id'=>$ticketId,
                'reason_code'=>$reasonCode,'result_status'=>(string)($result['status'] ?? 'completed'),
            ]);
            return array_merge(['success'=>(bool)($result['success'] ?? true),'status'=>(string)($result['status'] ?? 'completed'),'domain'=>$domain,'action'=>$action], $result);
        }

        if ($domain === 'reports_moderation') {
            $sourceType = (string) ($context['entity_type'] ?? '');
            $sourceId = (int) ($context['entity_id'] ?? 0);
            if (! in_array($sourceType, ['report','reported_message'], true) || $sourceId <= 0) {
                return ['success'=>false,'status'=>'invalid_context','reason'=>'moderation_entity_required'];
            }
            $result = $this->moderationCases->prepare($sourceType, $sourceId, $reasonCode);
            $this->events->emit('najm_hoda.founder_ops.low_risk.completed', [
                'domain'=>$domain,'action'=>$action,'entity_type'=>$sourceType,'entity_id'=>$sourceId,
                'reason_code'=>$reasonCode,'result_status'=>(string)($result['status'] ?? 'completed'),
            ]);
            return array_merge(['domain'=>$domain,'action'=>$action], $result);
        }

        $snapshot = $this->health->snapshot();
        $result = [
            'success'=>true,'status'=>'completed','domain'=>$domain,'action'=>$action,
            'health_status'=>(string)($snapshot['status'] ?? 'unknown'),
            'metrics'=>(array)($snapshot['metrics'] ?? []),
            'generated_at'=>(string)($snapshot['generated_at'] ?? now()->toIso8601String()),
        ];
        $this->events->emit('najm_hoda.founder_ops.low_risk.completed', [
            'domain'=>$domain,'action'=>$action,'health_status'=>$result['health_status'],'reason_code'=>$reasonCode,
        ]);
        return $result;
    }
}
