<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderFinancialRiskFinding;

class FounderAttentionService
{
    public function __construct(
        protected FounderOperationsSnapshotService $snapshots,
        protected FounderApprovalInboxService $approvalInbox,
        protected FounderAuthoritySnapshotService $authoritySnapshot
    ) {}

    public function brief(int $hours = 24): array
    {
        $snapshot = $this->snapshots->snapshot($hours);
        $approvalSnapshot = $this->approvalInbox->snapshot();
        $authoritySnapshot = $this->authoritySnapshot->snapshot();
        $items = [];

        $runtimeStatus = (string) data_get($snapshot, 'runtime_health.status', 'healthy');
        if ($runtimeStatus === 'critical') $items[] = $this->item('P0', 'runtime_health', 'وضعیت اجرای نجم هدا بحرانی است');
        elseif ($runtimeStatus === 'warning') $items[] = $this->item('P1', 'runtime_health', 'وضعیت اجرای نجم هدا نیازمند توجه است');

        $reconciliationRequired=(int)data_get($snapshot,'stock.settlement_allocations.reconciliation_required',0);
        if($reconciliationRequired>0){
            $items[]=$this->item('P0','stock','دریافت وجه بیرونی تأیید شده اما تخصیص سهام نیازمند تطبیق و رفع مغایرت است',['count'=>$reconciliationRequired]);
        }

        foreach (['critical'=>'P0','high'=>'P1','medium'=>'P2'] as $severity=>$priority) {
            $findings = FounderFinancialRiskFinding::query()->where('status','open')->where('severity',$severity)->get(['domain','risk_code']);
            if ($findings->isEmpty()) continue;
            $items[] = $this->item($priority, 'financial_risk', 'موارد بازِ سلامت و یکپارچگی مالی نیازمند رسیدگی هستند', [
                'severity'=>$severity,'count'=>$findings->count(),
                'by_domain'=>$findings->groupBy('domain')->map->count()->all(),
                'by_code'=>$findings->groupBy('risk_code')->map->count()->all(),
            ]);
        }

        $overdueFounderApprovals = (int) data_get($approvalSnapshot, 'overdue', 0);
        if ($overdueFounderApprovals > 0) {
            $items[] = $this->item('P1', 'founder_approvals', 'مهلت رسیدگی به برخی درخواست‌های تأیید مدیرکل گذشته است', ['count'=>$overdueFounderApprovals,'pending_total'=>(int)data_get($approvalSnapshot,'pending',0)]);
        }

        $rules = [
            ['P1','support','support.high_priority_active','تیکت‌های پشتیبانی با اولویت بالا فعال هستند'],
            ['P1','support','support.unassigned_active','تیکت‌های پشتیبانی فعال بدون مسئول باقی مانده‌اند'],
            ['P1','governance','governance.overdue_open','زمان پایان تنظیم‌شده برخی انتخابات باز گذشته است'],
            ['P1','reports_moderation','moderation.escalated_to_admin','گزارش‌های نظارتی به مدیر مرکزی ارجاع شده‌اند'],
            ['P1','stock','stock.expired_unsettled','برخی مزایده‌های سهام منقضی شده اما تسویه نشده‌اند'],
            ['P1','stock','stock.external_payment_intents.failed','برخی پرداخت‌های سرمایه بیرونی ناموفق بوده‌اند'],
            ['P1','stock','stock.external_payment_intents.expired_non_terminal','برخی پرداخت‌های سرمایه بیرونی پیش از تطبیق منقضی شده‌اند'],
            ['P1','secretariat','secretariat.overdue_dispatches','مهلت برخی ارسال‌های دبیرخانه گذشته است'],
            ['P1','najm_bahar','najm_bahar.scheduled_overdue','زمان اجرای برخی تراکنش‌های زمان‌بندی‌شده نجم بهار گذشته است'],
            ['P2','governance','governance.ending_within_24h','برخی انتخابات فعال در ۲۴ ساعت آینده پایان می‌یابند'],
            ['P2','reports_moderation','moderation.pending_group_manager','گزارش‌هایی در انتظار بررسی مدیر گروه هستند'],
            ['P2','invitations','growth.pending_invitation_requests','درخواست‌های دعوت در انتظار بررسی هستند'],
            ['P2','stock','stock.ending_within_24h','برخی مزایده‌های سهام در ۲۴ ساعت آینده پایان می‌یابند'],
            ['P2','secretariat','secretariat.dispatches_due_within_24h','مهلت برخی ارسال‌های دبیرخانه در ۲۴ ساعت آینده فرا می‌رسد'],
            ['P2','secretariat','secretariat.responses_due','برخی مکاتبات دبیرخانه در انتظار پاسخ هستند'],
            ['P2','najm_bahar','najm_bahar.projects_submitted','پروژه‌هایی در نجم بهار در انتظار بررسی هستند'],
            ['P2','najm_bahar','najm_bahar.scheduled_due_within_24h','برخی تراکنش‌های زمان‌بندی‌شده نجم بهار در ۲۴ ساعت آینده سررسید می‌شوند'],
            ['P2','content','content.faq_pending','پرسش‌هایی در بخش پرسش‌های متداول در انتظار پاسخ هستند'],
        ];
        foreach ($rules as [$priority, $domain, $path, $title]) {
            $count = (int) data_get($snapshot, $path, 0);
            if ($count > 0) $items[] = $this->item($priority, $domain, $title, ['count' => $count]);
        }

        $pendingFounderApprovals=(int)data_get($approvalSnapshot,'pending',0);
        if($pendingFounderApprovals>0&&$overdueFounderApprovals===0){
            $items[]=$this->item('P2','founder_approvals','اقدام‌هایی از نجم هدا در انتظار تأیید صریح مدیرکل هستند',['count'=>$pendingFounderApprovals,'by_risk'=>data_get($approvalSnapshot,'by_risk',[])]);
        }

        $pendingApprovals=(int)data_get($snapshot,'approvals.total',0);
        if($pendingApprovals>0){
            $items[]=$this->item('P2','approvals','داده‌های پایه مانند مکان، صنف یا تخصص در انتظار تأیید هستند',['count'=>$pendingApprovals,'references'=>data_get($snapshot,'approvals.references.by_type',[]),'locations'=>data_get($snapshot,'approvals.locations.by_type',[])]);
        }

        $sensitiveConfigChanges=collect((array)data_get($snapshot,'recent_managed_events',[]))->filter(fn(array $event):bool=>str_starts_with((string)($event['event']??''),'najm_hoda.input.admin_settings.'))->count();
        if($sensitiveConfigChanges>0)$items[]=$this->item('P2','admin_settings','تنظیمات حساس مدیریتی در بازه گزارش تغییر کرده‌اند',['events'=>$sensitiveConfigChanges]);

        foreach ([
            ['users','users.new_members','اعضای جدید در بازه گزارش به EarthCoop پیوسته‌اند'],
            ['groups','groups.created_in_window','گروه‌های جدید سیستمی یا اجتماعی ایجاد شده‌اند'],
            ['notifications','notifications.announcements_in_window','اطلاعیه‌هایی در بازه گزارش منتشر شده‌اند'],
            ['blog','blog.published_in_window','مطالب جدیدی در وبلاگ منتشر شده‌اند'],
            ['invitations','growth.used_codes_in_window','کدهای دعوت به ثبت‌نام موفق منجر شده‌اند'],
            ['najm_bahar','najm_bahar.review_events_in_window','رویدادهای بررسی پروژه در نجم بهار رخ داده‌اند'],
            ['stock','stock.external_payment_intents.confirmed','پرداخت‌های سرمایه بیرونی تأیید شده‌اند'],
            ['stock','stock.settlement_allocations.settled','تخصیص‌های رسمی سهام تسویه شده‌اند'],
        ] as [$domain,$path,$title]){
            $count=(int)data_get($snapshot,$path,0); if($count>0)$items[]=$this->item('P3',$domain,$title,['count'=>$count]);
        }

        $activeDelegations=(int)data_get($authoritySnapshot,'active_delegations_count',0);
        if($activeDelegations>0)$items[]=$this->item('P3','authority','اختیارهای واگذارشده و فعال مدیرکل به نجم هدا وجود دارند',['count'=>$activeDelegations,'actions'=>data_get($authoritySnapshot,'active_delegations',[])]);

        $rolloutQueue=(array)data_get($snapshot,'management_coverage.next_domains',[]);
        if($rolloutQueue!==[]&&is_array($rolloutQueue[0]??null)){
            $next=$rolloutQueue[0];
            $items[]=$this->item('P3','management_coverage','حوزه مدیریتی بعدی برای تکمیل اتصال به نجم هدا آماده است',['domain'=>$next['key']??null,'label'=>$next['label']??null,'stage'=>$next['integration_stage']??null,'risk'=>$next['risk']??null]);
        }

        usort($items,static function(array $a,array $b):int{$rank=['P0'=>0,'P1'=>1,'P2'=>2,'P3'=>3];return($rank[$a['priority']]??99)<=>($rank[$b['priority']]??99);});

        return [
            'generated_at'=>data_get($snapshot,'window.generated_at'),
            'summary'=>['total_attention_items'=>count($items),'P0'=>$this->countPriority($items,'P0'),'P1'=>$this->countPriority($items,'P1'),'P2'=>$this->countPriority($items,'P2'),'P3'=>$this->countPriority($items,'P3')],
            'items'=>$items,'founder_approvals'=>$approvalSnapshot,'authority'=>$authoritySnapshot,'management_coverage'=>data_get($snapshot,'management_coverage',[]),
        ];
    }

    protected function item(string $priority,string $domain,string $title,array $context=[]): array
    {
        return ['priority'=>$priority,'domain'=>$domain,'title'=>$title,'context'=>$context,'requires_founder_decision'=>in_array($priority,['P0','P1'],true)||$domain==='founder_approvals'];
    }

    protected function countPriority(array $items,string $priority): int
    {
        return count(array_filter($items,static fn(array $item):bool=>($item['priority']??null)===$priority));
    }
}
