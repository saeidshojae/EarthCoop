<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Ticket;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Services\SecretariatFollowUpProposalService;
use App\Modules\Stock\Models\Auction;
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
        protected SecretariatFollowUpProposalService $secretariatFollowUps,
        protected FounderStockRiskService $stockRisks,
        protected FounderNajmBaharRiskService $baharRisks,
        protected RuntimeEventBus $events
    ) {}

    public function supports(string $domain, string $action): bool
    {
        return in_array($domain . '.' . $action, [
            'runtime_health.collect_health_snapshot','runtime_health.run_read_only_diagnostic',
            'support.classify_ticket','support.assign_priority','support.draft_reply',
            'reports_moderation.prepare_case_summary','reports_moderation.classify_report',
            'secretariat.prepare_follow_up',
            'stock.summarize_auction','stock.flag_settlement_issue',
            'najm_bahar.flag_transaction_anomaly',
        ], true);
    }

    public function execute(string $domain, string $action, array $context = []): array
    {
        if (! $this->supports($domain, $action)) return ['success'=>false,'status'=>'unsupported','reason'=>'no_canonical_low_risk_handler'];
        $reasonCode=is_scalar($context['reason_code']??null)?(string)$context['reason_code']:null;

        if ($domain==='support') {
            $ticketId=(int)($context['entity_id']??0); $ticket=$ticketId>0?Ticket::query()->find($ticketId):null;
            if (!$ticket) return ['success'=>false,'status'=>'not_found','reason'=>'ticket_not_found'];
            if (!in_array((string)$ticket->status,['open','in-progress'],true)) return ['success'=>false,'status'=>'skipped','reason'=>'ticket_not_active'];
            $result=match($action){'classify_ticket'=>$this->tickets->classify($ticket),'assign_priority'=>$this->tickets->assignPriority($ticket),'draft_reply'=>$this->replyDrafts->generate($ticket,$reasonCode),default=>['success'=>false,'status'=>'unsupported']};
            return $this->complete($domain,$action,'ticket',$ticketId,$reasonCode,$result);
        }

        if ($domain==='reports_moderation') {
            $type=(string)($context['entity_type']??''); $id=(int)($context['entity_id']??0);
            if (!in_array($type,['report','reported_message'],true)||$id<=0) return ['success'=>false,'status'=>'invalid_context','reason'=>'moderation_entity_required'];
            return $this->complete($domain,$action,$type,$id,$reasonCode,$this->moderationCases->prepare($type,$id,$reasonCode));
        }

        if ($domain==='secretariat') {
            $id=(int)($context['entity_id']??0); $dispatch=$id>0?SecretariatDispatch::query()->find($id):null;
            if (!$dispatch) return ['success'=>false,'status'=>'not_found','reason'=>'secretariat_dispatch_not_found'];
            return $this->complete($domain,$action,'secretariat_dispatch',$id,$reasonCode,$this->secretariatFollowUps->prepare($dispatch,$reasonCode));
        }

        if ($domain==='stock') {
            $id=(int)($context['entity_id']??0); $auction=$id>0?Auction::query()->find($id):null;
            if (!$auction) return ['success'=>false,'status'=>'not_found','reason'=>'auction_not_found'];
            return $this->complete($domain,$action,'auction',$id,$reasonCode,$this->stockRisks->inspect($auction));
        }

        if ($domain==='najm_bahar') {
            $id=(int)($context['entity_id']??0); $scheduled=$id>0?ScheduledTransaction::query()->find($id):null;
            if (!$scheduled) return ['success'=>false,'status'=>'not_found','reason'=>'scheduled_transaction_not_found'];
            return $this->complete($domain,$action,'scheduled_transaction',$id,$reasonCode,$this->baharRisks->inspectScheduled($scheduled));
        }

        $snapshot=$this->health->snapshot();
        $result=['success'=>true,'status'=>'completed','domain'=>$domain,'action'=>$action,'health_status'=>(string)($snapshot['status']??'unknown'),'metrics'=>(array)($snapshot['metrics']??[]),'generated_at'=>(string)($snapshot['generated_at']??now()->toIso8601String())];
        $this->events->emit('najm_hoda.founder_ops.low_risk.completed',['domain'=>$domain,'action'=>$action,'health_status'=>$result['health_status'],'reason_code'=>$reasonCode]);
        return $result;
    }

    protected function complete(string $domain,string $action,string $entityType,int $entityId,?string $reasonCode,array $result): array
    {
        $this->events->emit('najm_hoda.founder_ops.low_risk.completed',['domain'=>$domain,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,'reason_code'=>$reasonCode,'result_status'=>(string)($result['status']??'completed'),'finding_count'=>(int)($result['finding_count']??0)]);
        return array_merge(['success'=>(bool)($result['success']??true),'status'=>(string)($result['status']??'completed'),'domain'=>$domain,'action'=>$action],$result);
    }
}
