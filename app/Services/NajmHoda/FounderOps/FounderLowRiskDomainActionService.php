<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Blog;
use App\Models\EmailTemplate;
use App\Models\FaqQuestion;
use App\Models\Page;
use App\Models\Ticket;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Services\SecretariatFollowUpProposalService;
use App\Modules\Stock\Models\Auction;
use App\Services\Content\ContentManagementService;
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
        protected FounderSecretariatCorrespondenceDraftService $secretariatDrafts,
        protected FounderStockRiskService $stockRisks,
        protected FounderNajmBaharRiskService $baharRisks,
        protected FounderNajmBaharProjectReviewDraftService $projectReviewDrafts,
        protected FounderReadOnlyManagementService $readOnly,
        protected FounderReferenceApprovalCandidateService $referenceCandidates,
        protected FounderEmailDraftService $emailDrafts,
        protected FounderContentDraftService $contentDrafts,
        protected FounderAnnouncementDraftService $announcementDrafts,
        protected ContentManagementService $contentManagement,
        protected RuntimeEventBus $events
    ) {}

    public function supports(string $domain, string $action): bool
    {
        return in_array($domain . '.' . $action, [
            'runtime_health.collect_health_snapshot','runtime_health.classify_incident','runtime_health.run_read_only_diagnostic',
            'support.classify_ticket','support.assign_priority','support.draft_reply',
            'reference_data.detect_duplicate','locations.detect_duplicate',
            'groups.summarize_activity','groups.propose_action_item',
            'governance.summarize_election','governance.flag_anomaly',
            'invitations.summarize_growth',
            'admin_settings.audit_configuration',
            'reports_moderation.prepare_case_summary','reports_moderation.classify_report',
            'email.draft_email','email.preview_template',
            'blog.draft_post','blog.suggest_edit',
            'content.draft_faq_answer','content.draft_page_update',
            'notifications.draft_announcement',
            'secretariat.draft_correspondence','secretariat.prepare_follow_up',
            'stock.summarize_auction','stock.flag_settlement_issue',
            'najm_bahar.summarize_financial_state','najm_bahar.flag_transaction_anomaly','najm_bahar.draft_project_review',
        ], true);
    }

    public function execute(string $domain, string $action, array $context = []): array
    {
        if (! $this->supports($domain, $action)) return ['success'=>false,'status'=>'unsupported','reason'=>'no_canonical_low_risk_handler'];
        $reasonCode=is_scalar($context['reason_code']??null)?(string)$context['reason_code']:null;
        $hours=max(1,min((int)($context['window_hours']??24),168));

        if ($domain==='content') {
            $id=(int)($context['entity_id']??0);
            if ($action==='draft_faq_answer') {
                $question=$id>0?FaqQuestion::query()->find($id):null;
                if(!$question)return ['success'=>false,'status'=>'not_found','reason'=>'faq_question_not_found'];
                $answer=is_scalar($context['answer']??null)?trim((string)$context['answer']):null;
                if($answer==='')$answer=null;
                $category=is_scalar($context['category']??null)?trim((string)$context['category']):null;
                $result=$this->contentManagement->draftFaqAnswer($question,$answer,$category?:null);
                return $this->complete($domain,$action,'faq_question',$id,$reasonCode,$result);
            }

            $page=$id>0?Page::query()->find($id):null;
            if(!$page)return ['success'=>false,'status'=>'not_found','reason'=>'page_not_found'];
            $changes=is_array($context['changes']??null)?$context['changes']:$context;
            unset($changes['entity_id'],$changes['reason_code'],$changes['requested_by']);
            $result=$this->contentManagement->draftPageUpdate($page,$changes);
            return $this->complete($domain,$action,'page',$id,$reasonCode,$result);
        }

        if ($domain==='notifications' && $action==='draft_announcement') {
            $attributes=is_array($context['announcement']??null)?$context['announcement']:$context;
            $result=$this->announcementDrafts->draft(
                $attributes,
                $reasonCode,
                is_numeric($context['requested_by']??null)?(int)$context['requested_by']:null
            );
            return $this->complete($domain,$action,'founder_announcement_draft',(int)($result['draft_id']??0),$reasonCode,$result);
        }

        if ($domain==='email') {
            if ($action==='preview_template') {
                $templateId=(int)($context['template_id']??$context['entity_id']??0);
                $template=$templateId>0?EmailTemplate::query()->whereKey($templateId)->where('is_active',true)->first():null;
                if(!$template)return ['success'=>false,'status'=>'not_found','reason'=>'active_email_template_not_found'];
                $variables=is_array($context['variables']??null)?$context['variables']:[];
                $rendered=$template->render($variables);
                return $this->complete('email',$action,'email_template',$templateId,$reasonCode,[
                    'success'=>true,'status'=>'completed','template_id'=>$templateId,
                    'subject'=>$rendered['subject'],'body'=>$rendered['body'],
                    'unresolved_variables'=>$template->getAvailableVariables(),
                ]);
            }
            $recipients=is_array($context['recipients']??null)?$context['recipients']:[];
            $templateId=is_numeric($context['template_id']??null)?(int)$context['template_id']:null;
            $variables=is_array($context['variables']??null)?$context['variables']:[];
            $result=$this->emailDrafts->draft(
                $recipients,$templateId,
                is_scalar($context['subject']??null)?(string)$context['subject']:null,
                is_scalar($context['body']??null)?(string)$context['body']:null,
                $variables,$reasonCode,
                is_numeric($context['requested_by']??null)?(int)$context['requested_by']:null
            );
            return $this->complete('email',$action,'founder_email_draft',(int)($result['draft_id']??0),$reasonCode,$result);
        }

        if ($domain==='blog') {
            if ($action==='draft_post') {
                $result=$this->contentDrafts->draft(
                    (string)($context['title']??''),(string)($context['body']??''),
                    is_numeric($context['group_id']??null)?(int)$context['group_id']:null,
                    is_numeric($context['category_id']??null)?(int)$context['category_id']:null,
                    $reasonCode,is_numeric($context['requested_by']??null)?(int)$context['requested_by']:null
                );
                return $this->complete('blog',$action,'founder_content_draft',(int)($result['draft_id']??0),$reasonCode,$result);
            }

            $blogId=(int)($context['entity_id']??0);
            $blog=$blogId>0?Blog::query()->find($blogId):null;
            if(!$blog)return ['success'=>false,'status'=>'not_found','reason'=>'blog_post_not_found'];
            return $this->complete('blog',$action,'blog',$blogId,$reasonCode,[
                'success'=>true,'status'=>'completed','proposal'=>[
                    'blog_id'=>$blogId,
                    'current_title'=>(string)$blog->title,
                    'current_body'=>(string)$blog->content,
                    'suggested_title'=>is_scalar($context['suggested_title']??null)?(string)$context['suggested_title']:null,
                    'suggested_body'=>is_scalar($context['suggested_body']??null)?(string)$context['suggested_body']:null,
                    'requires_mutation'=>true,
                    'execution_mode'=>'approval_required_future_edit_adapter',
                ],
            ]);
        }

        if (in_array($domain,['reference_data','locations'],true) && $action==='detect_duplicate') {
            $type=(string)($context['entity_type']??''); $id=(int)($context['entity_id']??0);
            $allowed=$domain==='reference_data'?['occupational','experience']:['rural','region','neighborhood','street','alley'];
            if($id<=0||!in_array($type,$allowed,true)) return ['success'=>false,'status'=>'invalid_context','reason'=>'reference_entity_required'];
            $candidate=$this->referenceCandidates->candidate($type,$id);
            if($candidate===null) return ['success'=>false,'status'=>'not_found','reason'=>'pending_reference_not_found'];
            return $this->complete($domain,$action,$type,$id,$reasonCode,['success'=>true,'status'=>'completed','analysis'=>$candidate]);
        }

        if (in_array($domain,['groups','governance','invitations','admin_settings'],true)) {
            $result=$this->readOnly->summarize($domain,$hours);
            if ($domain==='governance' && $action==='flag_anomaly') {
                $summary=(array)($result['summary']??[]);
                $result['anomaly_detected']=((int)($summary['overdue_open']??0))>0;
                $result['reason']='read_only_governance_anomaly_scan';
            }
            if ($domain==='groups' && $action==='propose_action_item') {
                $summary=(array)($result['summary']??[]);
                $result['proposal']=['kind'=>'operational_review','created_in_window'=>(int)($summary['created_in_window']??0),'active_in_window'=>(int)($summary['active_in_window']??0),'requires_mutation'=>false];
            }
            return $this->complete($domain,$action,$domain,0,$reasonCode,$result);
        }

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
            if ($action==='draft_correspondence') {
                $result=$this->secretariatDrafts->draft($context);
                return $this->complete($domain,$action,'secretariat_record',(int)($result['record_id']??0),$reasonCode,$result);
            }
            $id=(int)($context['entity_id']??0); $dispatch=$id>0?SecretariatDispatch::query()->find($id):null;
            if (!$dispatch) return ['success'=>false,'status'=>'not_found','reason'=>'secretariat_dispatch_not_found'];
            return $this->complete($domain,$action,'secretariat_dispatch',$id,$reasonCode,$this->secretariatFollowUps->prepare($dispatch,$reasonCode));
        }

        if ($domain==='stock') {
            $id=(int)($context['entity_id']??0); $auction=$id>0?Auction::query()->find($id):null;
            if (!$auction) return ['success'=>false,'status'=>'not_found','reason'=>'auction_not_found'];
            return $this->complete($domain,$action,'auction',$id,$reasonCode,$this->stockRisks->inspect($auction));
        }

        if ($domain==='najm_bahar' && $action==='summarize_financial_state') {
            return $this->complete($domain,$action,'najm_bahar',0,$reasonCode,$this->readOnly->summarize($domain,$hours));
        }
        if ($domain==='najm_bahar' && $action==='draft_project_review') {
            $id=(int)($context['entity_id']??$context['project_id']??0);
            $project=$id>0?Project::query()->find($id):null;
            if (!$project) return ['success'=>false,'status'=>'not_found','reason'=>'najm_bahar_project_not_found'];
            $result=$this->projectReviewDrafts->draft(
                $project,
                is_numeric($context['requested_by']??null)?(int)$context['requested_by']:null,
                $reasonCode
            );
            return $this->complete($domain,$action,'founder_najm_bahar_project_review_draft',(int)($result['draft_id']??0),$reasonCode,$result);
        }
        if ($domain==='najm_bahar') {
            $id=(int)($context['entity_id']??0); $scheduled=$id>0?ScheduledTransaction::query()->find($id):null;
            if (!$scheduled) return ['success'=>false,'status'=>'not_found','reason'=>'scheduled_transaction_not_found'];
            return $this->complete($domain,$action,'scheduled_transaction',$id,$reasonCode,$this->baharRisks->inspectScheduled($scheduled));
        }

        $snapshot=$this->health->snapshot();
        $result=['success'=>true,'status'=>'completed','domain'=>$domain,'action'=>$action,'health_status'=>(string)($snapshot['status']??'unknown'),'metrics'=>(array)($snapshot['metrics']??[]),'generated_at'=>(string)($snapshot['generated_at']??now()->toIso8601String())];
        if ($action==='classify_incident') $result['incident_class']=match($result['health_status']){'critical'=>'P0','warning'=>'P1','healthy'=>'none',default=>'P2'};
        $this->events->emit('najm_hoda.founder_ops.low_risk.completed',['domain'=>$domain,'action'=>$action,'health_status'=>$result['health_status'],'reason_code'=>$reasonCode]);
        return $result;
    }

    protected function complete(string $domain,string $action,string $entityType,int $entityId,?string $reasonCode,array $result): array
    {
        $this->events->emit('najm_hoda.founder_ops.low_risk.completed',['domain'=>$domain,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,'reason_code'=>$reasonCode,'result_status'=>(string)($result['status']??'completed'),'finding_count'=>(int)($result['finding_count']??0)]);
        return array_merge(['success'=>(bool)($result['success']??true),'status'=>(string)($result['status']??'completed'),'domain'=>$domain,'action'=>$action],$result);
    }
}
