<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderMinistryChatService
{
    public const INTENTS = [
        'morning_brief',
        'urgent_items',
        'pending_approvals',
        'communications',
        'system_health',
        'end_of_day',
        'users_registration',
        'reference_data',
        'support_moderation',
        'groups',
        'governance',
        'najm_bahar',
        'stock',
        'secretariat',
        'authority',
    ];

    protected array $queueCache = [];
    protected array $briefCache = [];

    public function __construct(
        protected FounderAttentionService $attention,
        protected FounderExecutiveWorkQueueService $workQueue,
        protected FounderApprovalInboxService $approvals,
        protected FounderOperationsSnapshotService $snapshots,
        protected FounderAuthoritySnapshotService $authority,
    ) {}

    public function inferIntent(string $message): ?string
    {
        $normalized = mb_strtolower(trim($message));
        if ($normalized === '') return null;

        foreach ([
            'ارسال کن', 'بفرست', 'منتشر کن', 'حذف کن', 'پاک کن', 'تأیید کن', 'تایید کن',
            'رد کن', 'اجرا کن', 'انجام بده', 'ببند', 'ثبت کن', 'ویرایش کن', 'تغییر بده',
            'approve ', 'reject ', 'delete ', 'send ', 'publish ', 'execute ',
        ] as $executionPhrase) {
            if (str_contains($normalized, mb_strtolower($executionPhrase))) return null;
        }

        $rules = [
            'morning_brief' => ['صبح مدیرکل', 'گزارش امروز', 'خلاصه امروز', 'از دیشب', 'امروز چه خبر', 'از آخرین حضورم'],
            'end_of_day' => ['پایان روز', 'جمع‌بندی روز', 'جمع بندی روز', 'امروز چه کردی', 'چه باقی مانده', 'چه باقی‌مانده'],
            'pending_approvals' => ['منتظر تأیید من', 'منتظر تایید من', 'تأیید من', 'تایید من', 'تصمیم من', 'approval', 'در انتظار تأیید'],
            'reference_data' => ['مکان', 'صنف', 'تخصص', 'داده پایه', 'داده‌های پایه', 'داده های پایه'],
            'support_moderation' => ['پشتیبانی', 'شکایت', 'گزارش نظارتی', 'مودریشن', 'moderation', 'تیکت'],
            'users_registration' => ['کاربر جدید', 'کاربران جدید', 'عضو جدید', 'اعضای جدید', 'ثبت نام', 'ثبت‌نام', 'دعوت'],
            'governance' => ['انتخابات', 'حکمرانی', 'رأی', 'رای'],
            'najm_bahar' => ['نجم بهار', 'بهار', 'پروژه مالی', 'تراکنش'],
            'stock' => ['سهام', 'مزایده', 'تامین مالی', 'تأمین مالی', 'settlement', 'تسویه'],
            'secretariat' => ['دبیرخانه', 'مکاتبه', 'مکاتبات', 'پیگیری'],
            'authority' => ['اختیار', 'واگذاری', 'تفویض', 'delegation'],
            'groups' => ['گروه', 'گروه‌ها', 'گروه ها'],
            'communications' => ['ارتباطات', 'ایمیل', 'اطلاعیه', 'محتوا', 'انتشار'],
            'system_health' => ['سلامت سامانه', 'سلامت سیستم', 'سلامت نجم', 'runtime', 'خطای سیستم', 'خطای سامانه'],
            'urgent_items' => ['کارهای فوری', 'موارد فوری', 'فوری', 'بحرانی', 'اولویت بالا', 'مهم‌ترین', 'مهم ترین'],
        ];

        foreach ($rules as $intent => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, mb_strtolower($needle))) return $intent;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function unclassifiedResponse(): array
    {
        return $this->response(
            false,
            'این سؤال هنوز به یکی از خدمات امن وزارت هوشمند نگاشت نشده است. از دکمه‌های وزارت استفاده کنید یا سؤال را درباره یکی از حوزه‌های مدیریتی بپرسید. برای اقدام اجرایی فقط از دکمه‌های صریح کارت‌ها یا میزکار مدیرکل استفاده کنید.',
            'unclassified',
            [],
            [],
            ['reason' => 'unclassified_management_question']
        );
    }

    /** @return array<string,mixed> */
    public function respond(string $intent, int $hours = 24): array
    {
        $hours = max(1, min($hours, 168));
        $this->queueCache = [];
        $this->briefCache = [];

        if (! in_array($intent, self::INTENTS, true)) {
            return $this->response(false, 'این درخواست در وزارت هوشمند ثبت نشده است.', $intent, [], [], ['reason' => 'unknown_management_intent']);
        }

        $result = match ($intent) {
            'morning_brief' => $this->morningBrief($hours),
            'urgent_items' => $this->urgentItems($hours),
            'pending_approvals' => $this->pendingApprovals(),
            'communications' => $this->communications($hours),
            'system_health' => $this->systemHealth($hours),
            'end_of_day' => $this->endOfDay($hours),
            'users_registration' => $this->snapshotDomain($hours, 'users_registration'),
            'reference_data' => $this->snapshotDomain($hours, 'reference_data'),
            'support_moderation' => $this->snapshotDomain($hours, 'support_moderation'),
            'groups' => $this->snapshotDomain($hours, 'groups'),
            'governance' => $this->snapshotDomain($hours, 'governance'),
            'najm_bahar' => $this->snapshotDomain($hours, 'najm_bahar'),
            'stock' => $this->snapshotDomain($hours, 'stock'),
            'secretariat' => $this->snapshotDomain($hours, 'secretariat'),
            'authority' => $this->authorityOverview(),
        };

        if (($result['success'] ?? false) === true) {
            $queue = $this->queue($hours);
            $brief = $this->brief($hours);
            $result['management']['global_summary_cards'] = $this->summaryCards($brief, $queue);
            $result['management']['items'] = $this->decorateItems((array) data_get($result, 'management.items', []));
        }

        return $result;
    }

    /** @return array<string,mixed> */
    protected function morningBrief(int $hours): array
    {
        $brief = $this->brief($hours);
        $queue = $this->queue($hours);
        $cards = $this->summaryCards($brief, $queue);
        $items = array_slice((array) data_get($queue, 'items', []), 0, 12);
        $message = sprintf('صبح بخیر. در %d ساعت اخیر %d مورد فوری/مهم، %d تصمیم منتظر شما، %d کار آماده‌شده توسط نجم هدا و %d مورد صرفاً جهت اطلاع دارم. موارد زیر به ترتیب اولویت‌اند.', $hours, (int) $cards['urgent'], (int) $cards['founder_decisions'], (int) $cards['prepared'], (int) $cards['information']);
        return $this->response(true, $message, 'morning_brief', $cards, $items, ['generated_at' => data_get($brief, 'generated_at'), 'window_hours' => $hours]);
    }

    /** @return array<string,mixed> */
    protected function urgentItems(int $hours): array
    {
        $queue = $this->queue($hours);
        $items = array_values(array_filter((array) data_get($queue, 'items', []), static fn ($item): bool => is_array($item) && in_array((string) ($item['priority'] ?? ''), ['P0', 'P1'], true)));
        return $this->response(true, count($items) > 0 ? 'این‌ها فوری‌ترین موضوعات مدیریتی فعلی هستند.' : 'در حال حاضر مورد P0 یا P1 ثبت نشده است.', 'urgent_items', ['urgent' => count($items)], array_slice($items, 0, 20), ['window_hours' => $hours]);
    }

    /** @return array<string,mixed> */
    protected function pendingApprovals(): array
    {
        $approvals = $this->approvals->snapshot(100);
        $items = array_values(array_filter((array) data_get($approvals, 'items', []), 'is_array'));
        $items = array_map(static function (array $item): array {
            $domain = (string) ($item['domain'] ?? 'unknown');
            $action = (string) ($item['domain_action'] ?? '');
            return [
                'kind' => 'approval',
                'priority' => (string) (($item['sla_status'] ?? '') === 'overdue' || in_array((string) ($item['risk'] ?? ''), ['critical', 'high'], true) ? 'P0' : 'P1'),
                'domain' => $domain,
                'action' => $action,
                'entity_type' => data_get($item, 'context.entity_type'),
                'entity_id' => data_get($item, 'context.entity_id'),
                'title' => (string) ($item['title'] ?? self::approvalTitle($domain, $action)),
                'status' => (string) ($item['sla_status'] ?? 'pending'),
                'approval_request_id' => $item['id'] ?? null,
                'risk' => $item['risk'] ?? null,
            ];
        }, $items);
        return $this->response(true, count($items) > 0 ? 'این تصمیم‌ها منتظر تأیید یا رد صریح شما هستند.' : 'در حال حاضر تصمیمی منتظر شما نیست.', 'pending_approvals', ['pending' => (int) data_get($approvals, 'pending', count($items)), 'overdue' => (int) data_get($approvals, 'overdue', 0)], array_slice($items, 0, 30), ['by_risk' => (array) data_get($approvals, 'by_risk', [])]);
    }

    /** @return array<string,mixed> */
    protected function communications(int $hours): array
    {
        $queue = $this->queue($hours);
        $domains = ['support', 'email', 'blog', 'notifications'];
        $items = array_values(array_filter((array) data_get($queue, 'items', []), static fn ($item): bool => is_array($item) && in_array((string) ($item['domain'] ?? ''), $domains, true)));
        $approvals = count(array_filter($items, static fn (array $item): bool => ($item['kind'] ?? '') === 'approval'));
        $prepared = count(array_filter($items, static fn (array $item): bool => ($item['kind'] ?? '') === 'proposal'));
        return $this->response(true, count($items) > 0 ? 'وضعیت ارتباطات، پشتیبانی و انتشارهای آماده/منتظر تصمیم را جمع‌بندی کردم.' : 'مورد ارتباطی آماده یا منتظر تصمیم ثبت نشده است.', 'communications', ['pending_decisions' => $approvals, 'prepared' => $prepared, 'total' => count($items)], array_slice($items, 0, 30), ['window_hours' => $hours]);
    }

    /** @return array<string,mixed> */
    protected function systemHealth(int $hours): array
    {
        $snapshot = $this->snapshots->snapshot($hours);
        $brief = $this->brief($hours);
        $healthItems = array_values(array_filter((array) data_get($brief, 'items', []), static fn ($item): bool => is_array($item) && in_array((string) ($item['domain'] ?? ''), ['runtime_health', 'financial_risk', 'stock', 'najm_bahar'], true)));
        $runtimeStatus = (string) data_get($snapshot, 'runtime_health.status', 'unknown');
        return $this->response(true, $runtimeStatus === 'healthy' ? 'سلامت runtime نجم هدا در snapshot فعلی سالم گزارش شده است؛ هشدارهای مرتبط در کارت‌های زیر آمده‌اند.' : 'سلامت runtime نیازمند توجه است؛ جزئیات و هشدارهای مرتبط را در کارت‌های زیر ببینید.', 'system_health', ['runtime_status' => $runtimeStatus, 'health_attention_items' => count($healthItems)], array_slice($healthItems, 0, 20), ['window_hours' => $hours]);
    }

    /** @return array<string,mixed> */
    protected function endOfDay(int $hours): array
    {
        $brief = $this->brief($hours);
        $queue = $this->queue($hours);
        $cards = $this->summaryCards($brief, $queue);
        $items = array_slice((array) data_get($queue, 'items', []), 0, 12);
        $message = sprintf('جمع‌بندی فعلی: %d تصمیم هنوز منتظر شماست، %d کار آماده بررسی است و %d مورد مدیریتی فقط برای اطلاع باقی مانده. این گزارش وضعیت اکنون است و ادعای انجام کاری خارج از رویدادهای ثبت‌شده ندارد.', (int) $cards['founder_decisions'], (int) $cards['prepared'], (int) $cards['information']);
        return $this->response(true, $message, 'end_of_day', $cards, $items, ['generated_at' => data_get($brief, 'generated_at'), 'window_hours' => $hours]);
    }

    /** @return array<string,mixed> */
    protected function snapshotDomain(int $hours, string $intent): array
    {
        $snapshot = $this->snapshots->snapshot($hours);
        $queue = $this->queue($hours);
        $config = $this->domainConfig($intent);
        $domains = $config['domains'];
        $items = array_values(array_filter((array) data_get($queue, 'items', []), static fn ($item): bool => is_array($item) && in_array((string) ($item['domain'] ?? ''), $domains, true)));
        $cards = [];
        foreach ($config['metrics'] as $key => [$label, $path]) {
            $cards[$key] = ['label' => $label, 'value' => (int) data_get($snapshot, $path, 0)];
        }
        return $this->response(true, $config['message'], $intent, $cards, array_slice($items, 0, 30), ['window_hours' => $hours, 'workbench_anchor' => $config['anchor']]);
    }

    /** @return array<string,mixed> */
    protected function authorityOverview(): array
    {
        $snapshot = $this->authority->snapshot();
        $items = [];
        foreach ((array) data_get($snapshot, 'active_delegations', []) as $delegation) {
            if (! is_array($delegation)) continue;
            $items[] = ['kind'=>'attention','priority'=>'P3','domain'=>'authority','title'=>(string)($delegation['action'] ?? 'اختیار واگذارشده فعال'),'status'=>'active','context'=>$delegation];
        }
        return $this->response(true, count($items) > 0 ? 'اختیارهای واگذارشده فعال مدیرکل را جمع‌بندی کردم.' : 'در حال حاضر اختیار واگذارشده فعالی ثبت نشده است.', 'authority', [
            'active_delegations' => ['label'=>'اختیار فعال','value'=>(int) data_get($snapshot, 'active_delegations_count', count($items))],
            'total_actions' => ['label'=>'اقدام تعریف‌شده','value'=>(int) data_get($snapshot, 'total_actions', 0)],
            'pending_approvals' => ['label'=>'تأیید منتظر','value'=>(int) data_get($snapshot, 'pending_approvals_count', 0)],
            'overdue_approvals' => ['label'=>'تأیید عقب‌افتاده','value'=>(int) data_get($snapshot, 'overdue_approvals_count', 0)],
        ], $items, ['fail_closed'=>(bool)data_get($snapshot,'fail_closed',true),'workbench_anchor'=>'#technical-status']);
    }

    /** @return array<string,mixed> */
    protected function domainConfig(string $intent): array
    {
        return match ($intent) {
            'users_registration' => ['domains'=>['users','invitations'],'anchor'=>'#system-status','message'=>'وضعیت کاربران جدید، ثبت‌نام و دعوت‌ها را جمع‌بندی کردم.','metrics'=>[
                'new_members'=>['اعضای جدید','users.new_members'], 'verified'=>['عضو جدید تأییدشده','users.new_verified_members'], 'pending_invitations'=>['دعوت‌های منتظر','growth.pending_invitation_requests'], 'used_codes'=>['دعوت موفق','growth.used_codes_in_window'],
            ]],
            'reference_data' => ['domains'=>['reference_data','locations','approvals'],'anchor'=>'#reference-data','message'=>'مکان‌ها، صنف‌ها، تخصص‌ها و سایر داده‌های پایه منتظر بررسی را جمع‌بندی کردم.','metrics'=>[
                'pending'=>['کل داده منتظر','approvals.total'], 'reference_pending'=>['صنف/تخصص و داده مرجع','approvals.references.total'], 'location_pending'=>['مکان‌های منتظر','approvals.locations.total'],
            ]],
            'support_moderation' => ['domains'=>['support','reports_moderation','moderation'],'anchor'=>'#support','message'=>'پشتیبانی، شکایات و پرونده‌های نظارتی را جمع‌بندی کردم.','metrics'=>[
                'open_tickets'=>['تیکت باز','support.open'], 'in_progress'=>['در حال رسیدگی','support.in_progress'], 'high_priority'=>['پشتیبانی فوری','support.high_priority_active'], 'escalated_reports'=>['گزارش ارجاع‌شده','moderation.escalated_to_admin'],
            ]],
            'groups' => ['domains'=>['groups'],'anchor'=>'#system-status','message'=>'تغییرات و وضعیت قابل‌مشاهده گروه‌ها را جمع‌بندی کردم.','metrics'=>[
                'total'=>['کل گروه‌ها','groups.total'], 'open'=>['گروه باز','groups.open'], 'active'=>['فعال در بازه','groups.active_in_window'], 'created'=>['گروه جدید','groups.created_in_window'],
            ]],
            'governance' => ['domains'=>['governance'],'anchor'=>'#system-status','message'=>'وضعیت انتخابات و حکمرانی را جمع‌بندی کردم.','metrics'=>[
                'active'=>['انتخابات فعال','governance.active_elections'], 'overdue'=>['انتخابات عقب‌افتاده','governance.overdue_open'], 'ending_soon'=>['پایان تا ۲۴ ساعت','governance.ending_within_24h'], 'started'=>['شروع‌شده در بازه','governance.started_in_window'],
            ]],
            'najm_bahar' => ['domains'=>['najm_bahar','financial_risk'],'anchor'=>'#system-status','message'=>'وضعیت نجم بهار، پروژه‌ها، تراکنش‌های زمان‌بندی‌شده و هشدارهای مالی را جمع‌بندی کردم.','metrics'=>[
                'submitted'=>['پروژه ارسال‌شده','najm_bahar.projects_submitted'], 'under_review'=>['پروژه در بررسی','najm_bahar.projects_under_review'], 'revision'=>['نیازمند اصلاح','najm_bahar.revision_requested'], 'scheduled_overdue'=>['تراکنش عقب‌افتاده','najm_bahar.scheduled_overdue'],
            ]],
            'stock' => ['domains'=>['stock'],'anchor'=>'#system-status','message'=>'وضعیت سهام، مزایده‌ها، پرداخت و تطبیق تسویه را جمع‌بندی کردم.','metrics'=>[
                'running'=>['مزایده فعال','stock.running_auctions'], 'reconciliation'=>['نیازمند تطبیق','stock.settlement_allocations.reconciliation_required'], 'expired_unsettled'=>['منقضی تسویه‌نشده','stock.expired_unsettled'], 'failed_payments'=>['پرداخت ناموفق','stock.external_payment_intents.failed'],
            ]],
            'secretariat' => ['domains'=>['secretariat'],'anchor'=>'#secretariat','message'=>'پرونده‌ها، ارسال‌ها و پیگیری‌های دبیرخانه را جمع‌بندی کردم.','metrics'=>[
                'open_cases'=>['پرونده باز','secretariat.open_cases'], 'overdue_dispatches'=>['ارسال عقب‌افتاده','secretariat.overdue_dispatches'], 'due_soon'=>['سررسید تا ۲۴ ساعت','secretariat.dispatches_due_within_24h'], 'responses_due'=>['پاسخ منتظر','secretariat.responses_due'],
            ]],
            default => ['domains'=>[],'anchor'=>'#system-status','message'=>'گزارش حوزه آماده شد.','metrics'=>[]],
        };
    }

    /** @return array<int,array<string,mixed>> */
    protected function decorateItems(array $items): array
    {
        return array_map(function ($raw): array {
            $item = is_array($raw) ? $raw : [];
            $domain = (string) ($item['domain'] ?? 'unknown');
            $kind = (string) ($item['kind'] ?? 'attention');
            $item['ui'] = ['workbench_url'=>route('admin.najm-hoda.founder-ops.index').$this->anchorForDomain($domain),'actions'=>[]];

            if ($kind === 'approval' && ! empty($item['approval_request_id'])) {
                $routeName = $this->decisionRouteForDomain($domain);
                if ($routeName !== null) {
                    $url = route($routeName, (string) $item['approval_request_id']);
                    $item['ui']['actions'][] = ['type'=>'decision','method'=>'POST','url'=>$url,'decision'=>'approve','label'=>$this->approveLabel($domain),'style'=>'success','confirm'=>true];
                    $item['ui']['actions'][] = ['type'=>'decision','method'=>'POST','url'=>$url,'decision'=>'reject','label'=>'رد','style'=>'outline-danger','confirm'=>true];
                }
            }

            if ($kind === 'proposal' && ! empty($item['entity_id'])) {
                $routeName = $this->proposalRequestRoute((string) ($item['entity_type'] ?? ''));
                if ($routeName !== null) {
                    $item['ui']['actions'][] = ['type'=>'request_approval','method'=>'POST','url'=>route($routeName,(int)$item['entity_id']),'label'=>'ارسال برای تأیید نهایی','style'=>'primary','confirm'=>true];
                }
            }

            return $item;
        }, $items);
    }

    protected function decisionRouteForDomain(string $domain): ?string
    {
        return match ($domain) {
            'support' => 'admin.najm-hoda.founder-ops.support-approvals.decision',
            'reference_data', 'locations' => 'admin.najm-hoda.founder-ops.reference-approvals.decision',
            'reports_moderation', 'moderation' => 'admin.najm-hoda.founder-ops.moderation-approvals.decision',
            'email' => 'admin.najm-hoda.founder-ops.email-approvals.decision',
            'blog' => 'admin.najm-hoda.founder-ops.content-approvals.decision',
            'notifications' => 'admin.najm-hoda.founder-ops.announcement-approvals.decision',
            default => null,
        };
    }

    protected function proposalRequestRoute(string $entityType): ?string
    {
        return match ($entityType) {
            'support_reply_draft' => 'admin.najm-hoda.founder-ops.support-drafts.request-send',
            'founder_email_draft' => 'admin.najm-hoda.founder-ops.email-drafts.request-send',
            'founder_content_draft' => 'admin.najm-hoda.founder-ops.content-drafts.request-publish',
            'founder_announcement_draft' => 'admin.najm-hoda.founder-ops.announcement-drafts.request-publish',
            default => null,
        };
    }

    protected function approveLabel(string $domain): string
    {
        return match ($domain) {
            'support', 'email' => 'تأیید و ارسال',
            'blog', 'notifications' => 'تأیید و انتشار',
            'reference_data', 'locations' => 'تأیید',
            'reports_moderation', 'moderation' => 'تأیید اقدام',
            default => 'تأیید',
        };
    }

    protected function anchorForDomain(string $domain): string
    {
        return match ($domain) {
            'support' => '#support',
            'reference_data', 'locations', 'approvals' => '#reference-data',
            'reports_moderation', 'moderation' => '#moderation',
            'email', 'blog', 'notifications' => '#communications',
            'secretariat' => '#secretariat',
            'founder_approvals' => '#decisions',
            'authority' => '#technical-status',
            default => '#system-status',
        };
    }

    protected static function approvalTitle(string $domain, string $action): string
    {
        $domainLabel = match ($domain) {
            'support'=>'پاسخ پشتیبانی','reference_data','locations'=>'داده پایه','reports_moderation','moderation'=>'پرونده نظارتی','email'=>'ایمیل','blog'=>'محتوا','notifications'=>'اطلاعیه',default=>'اقدام مدیریتی',
        };
        $actionLabel = match ($action) {
            'send_reply','send_email'=>'ارسال','publish_post','publish_announcement'=>'انتشار','approve'=>'تأیید','resolve_report'=>'رسیدگی نهایی',default=>'تصمیم',
        };
        return $domainLabel.' — '.$actionLabel.' منتظر تصمیم شماست';
    }

    /** @return array<string,mixed> */
    protected function queue(int $hours): array
    {
        return $this->queueCache[$hours] ??= $this->workQueue->snapshot($hours, 100);
    }

    /** @return array<string,mixed> */
    protected function brief(int $hours): array
    {
        return $this->briefCache[$hours] ??= $this->attention->brief($hours);
    }

    /** @return array<string,int> */
    protected function summaryCards(array $brief, array $queue): array
    {
        return ['urgent'=>(int)data_get($brief,'summary.P0',0)+(int)data_get($brief,'summary.P1',0),'founder_decisions'=>(int)data_get($queue,'needs_founder_decision',0),'prepared'=>(int)data_get($queue,'prepared_by_najm_hoda',0),'information'=>(int)data_get($queue,'attention_only',0)];
    }

    /** @return array<string,mixed> */
    protected function response(bool $success, string $message, string $intent, array $cards, array $items, array $meta = []): array
    {
        return ['success'=>$success,'message'=>$message,'agent'=>'steward','agent_name'=>'نجم هدا — وزارت هوشمند','agent_icon'=>'✦','suggestions'=>[],'management'=>['intent'=>$intent,'summary_cards'=>$cards,'items'=>$items,'meta'=>$meta]];
    }
}
