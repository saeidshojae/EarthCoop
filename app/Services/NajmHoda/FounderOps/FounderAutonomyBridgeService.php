<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class FounderAutonomyBridgeService
{
    public function __construct(
        protected FounderAttentionService $attention,
        protected FounderActionRequestService $requests,
        protected FounderSupportCandidateService $supportCandidates,
        protected FounderModerationCandidateService $moderationCandidates,
        protected FounderSecretariatCandidateService $secretariatCandidates,
        protected FounderStockCandidateService $stockCandidates,
        protected FounderNajmBaharCandidateService $baharCandidates,
        protected RuntimeEventBus $events
    ) {}

    public function plan(int $hours = 24, int $limit = 12): array
    {
        $brief=$this->attention->brief($hours); $items=array_slice((array)($brief['items']??[]),0,max(1,min($limit,50))); $prepared=[];

        foreach ($this->supportCandidates->candidates(min(5,$limit)) as $candidate) {
            $id=(int)($candidate['ticket_id']??0); if($id<=0) continue;
            foreach(['classify_ticket','assign_priority','draft_reply'] as $action){$context=['entity_type'=>'ticket','entity_id'=>$id,'attention_priority'=>($candidate['priority']??null)==='high'?'P1':'P2','reason_code'=>substr(hash('sha256','support|'.$action.'|'.$id),0,20),'source_event'=>'founder_support_candidate'];$prepared[]=$this->prepared('support',$action,$context,'Support ticket requires operational triage');}
        }

        foreach ($this->moderationCandidates->candidates(min(5,$limit)) as $candidate) {
            $type=(string)($candidate['source_type']??''); $id=(int)($candidate['source_id']??0); if($id<=0||!in_array($type,['report','reported_message'],true)) continue;
            foreach(['classify_report','prepare_case_summary'] as $action){$context=['entity_type'=>$type,'entity_id'=>$id,'attention_priority'=>in_array(($candidate['priority']??''),['critical','high'],true)?'P1':'P2','reason_code'=>substr(hash('sha256','moderation|'.$action.'|'.$type.'|'.$id),0,20),'source_event'=>'founder_moderation_candidate'];$prepared[]=$this->prepared('reports_moderation',$action,$context,'Moderation report requires review');}
        }

        foreach ($this->secretariatCandidates->candidates(min(5,$limit)) as $candidate) {
            $id=(int)($candidate['dispatch_id']??0); if($id<=0) continue;
            $context=['entity_type'=>'secretariat_dispatch','entity_id'=>$id,'attention_priority'=>($candidate['urgency']??'normal')==='high'?'P1':'P2','reason_code'=>substr(hash('sha256','secretariat|prepare_follow_up|'.$id),0,20),'source_event'=>'founder_secretariat_candidate'];
            $prepared[]=$this->prepared('secretariat','prepare_follow_up',$context,'Secretariat follow-up is due');
        }

        foreach ($this->stockCandidates->candidates(min(5,$limit)) as $candidate) {
            $id=(int)($candidate['auction_id']??0); if($id<=0) continue;
            $context=['entity_type'=>'auction','entity_id'=>$id,'attention_priority'=>($candidate['urgency']??'normal')==='high'?'P1':'P2','reason_code'=>substr(hash('sha256','stock|flag_settlement_issue|'.$id),0,20),'source_event'=>'founder_stock_candidate'];
            $prepared[]=$this->prepared('stock','flag_settlement_issue',$context,'Stock auction settlement boundary requires audit');
        }

        foreach ($this->baharCandidates->candidates(min(5,$limit)) as $candidate) {
            $id=(int)($candidate['scheduled_transaction_id']??0); if($id<=0) continue;
            $context=['entity_type'=>'scheduled_transaction','entity_id'=>$id,'attention_priority'=>($candidate['urgency']??'normal')==='high'?'P1':'P2','reason_code'=>substr(hash('sha256','bahar|flag_transaction_anomaly|'.$id),0,20),'source_event'=>'founder_bahar_candidate'];
            $prepared[]=$this->prepared('najm_bahar','flag_transaction_anomaly',$context,'Najm Bahar scheduled transaction requires anomaly audit');
        }

        foreach($items as $item){
            if(!is_array($item)||in_array((string)($item['domain']??''),['support','reports_moderation','secretariat','stock','najm_bahar'],true)) continue;
            $mapped=$this->mapAttentionToAction($item); if($mapped===null) continue; [$domain,$action]=$mapped;
            $context=['attention_priority'=>(string)($item['priority']??''),'reason_code'=>$this->reasonCode($item),'source_event'=>'founder_attention'];
            $entityId=data_get($item,'context.entity_id'); if(is_numeric($entityId)){ $context['entity_id']=(int)$entityId; $context['entity_type']=(string)data_get($item,'context.entity_type',$domain); }
            $prepared[]=$this->prepared($domain,$action,$context,(string)($item['title']??'Founder attention item'));
        }

        $prepared=array_slice($prepared,0,max(1,min($limit,50)));
        $summary=['total'=>count($prepared),'awaiting_approval'=>$this->countStatus($prepared,'awaiting_approval'),'delegated_ready'=>$this->countStatus($prepared,'delegated_ready'),'delegation_required'=>$this->countStatus($prepared,'delegation_required'),'proposal_only'=>$this->countStatus($prepared,'proposal_only'),'read_only'=>$this->countStatus($prepared,'read_only'),'blocked'=>$this->countStatus($prepared,'blocked')];
        $this->events->emit('najm_hoda.founder_ops.autonomy.plan_prepared',['count'=>$summary['total'],'awaiting_approval'=>$summary['awaiting_approval'],'delegated_ready'=>$summary['delegated_ready'],'blocked'=>$summary['blocked']]);
        return ['generated_at'=>now()->toIso8601String(),'attention_summary'=>$brief['summary']??[],'summary'=>$summary,'actions'=>$prepared];
    }

    protected function prepared(string $domain,string $action,array $context,string $title): array
    { return ['source_attention'=>['priority'=>$context['attention_priority']??'P2','domain'=>$domain,'title'=>$title],'domain'=>$domain,'action'=>$action,'action_context'=>$context,'preparation'=>$this->requests->prepare($domain,$action,$context)]; }

    protected function mapAttentionToAction(array $item): ?array
    { return match((string)($item['domain']??'')){'governance'=>['governance','flag_anomaly'],'content'=>['content','draft_faq_answer'],'approvals'=>['reference_data','recommend_approval'],'invitations'=>['invitations','recommend_request_decision'],'admin_settings'=>['admin_settings','recommend_change'],'runtime_health'=>['runtime_health','run_read_only_diagnostic'],'users'=>['users','draft_support_response'],'groups'=>['groups','propose_action_item'],'notifications'=>['notifications','draft_announcement'],'blog'=>['blog','suggest_edit'],default=>null}; }
    protected function reasonCode(array $item): string { return substr(hash('sha256',implode('|',[(string)($item['priority']??''),(string)($item['domain']??''),(string)($item['title']??'')])),0,20); }
    protected function countStatus(array $prepared,string $status): int { return count(array_filter($prepared,static fn(array $item): bool => (string)data_get($item,'preparation.status','')===$status)); }
}
