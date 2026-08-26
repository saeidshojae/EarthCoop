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

    private const ACTION_TITLES = [
        'users.view_profile_summary' => 'خلاصه وضعیت کاربر را بررسی کنم',
        'users.draft_support_response' => 'پاسخ پشتیبانی را آماده کنم',
        'users.send_support_response' => 'پاسخ پشتیبانی را ارسال کنم',
        'users.suspend_user' => 'دسترسی کاربر را تعلیق کنم',
        'users.delete_user' => 'کاربر را حذف کنم',
        'support.classify_ticket' => 'تیکت را دسته‌بندی کنم',
        'support.assign_priority' => 'اولویت تیکت را تعیین کنم',
        'support.draft_reply' => 'پاسخ تیکت را آماده کنم',
        'support.send_reply' => 'پاسخ تیکت را ارسال کنم',
        'support.close_ticket' => 'تیکت را ببندم',
        'reference_data.detect_duplicate' => 'موارد تکراری داده پایه را شناسایی کنم',
        'reference_data.recommend_approval' => 'برای داده پایه پیشنهاد تأیید یا رد آماده کنم',
        'reference_data.approve' => 'داده پایه را تأیید کنم',
        'reference_data.reject' => 'داده پایه را رد کنم',
        'reference_data.delete' => 'داده پایه را حذف کنم',
        'locations.detect_duplicate' => 'مکان‌های تکراری را شناسایی کنم',
        'locations.recommend_approval' => 'برای مکان جدید پیشنهاد تأیید یا رد آماده کنم',
        'locations.approve' => 'مکان جدید را تأیید کنم',
        'locations.reject' => 'مکان جدید را رد کنم',
        'locations.delete' => 'مکان را حذف کنم',
        'groups.summarize_activity' => 'فعالیت گروه‌ها را جمع‌بندی کنم',
        'groups.propose_action_item' => 'اقدام مدیریتی برای گروه پیشنهاد کنم',
        'groups.change_member_role' => 'نقش عضو گروه را تغییر دهم',
        'groups.close_group' => 'گروه را ببندم',
        'groups.delete_group' => 'گروه را حذف کنم',
        'governance.summarize_election' => 'وضعیت انتخابات را جمع‌بندی کنم',
        'governance.flag_anomaly' => 'ناهنجاری انتخاباتی را علامت‌گذاری کنم',
        'governance.change_election_rules' => 'قواعد انتخابات را تغییر دهم',
        'governance.alter_vote' => 'رأی ثبت‌شده را تغییر دهم',
        'governance.alter_result' => 'نتیجه انتخابات را تغییر دهم',
        'reports_moderation.classify_report' => 'گزارش یا شکایت را دسته‌بندی کنم',
        'reports_moderation.prepare_case_summary' => 'خلاصه پرونده شکایت را آماده کنم',
        'reports_moderation.resolve_report' => 'پرونده گزارش را مختومه کنم',
        'reports_moderation.sanction_user' => 'برای کاربر اقدام انضباطی اعمال کنم',
        'email.draft_email' => 'ایمیل را آماده کنم',
        'email.preview_template' => 'پیش‌نمایش قالب ایمیل را آماده کنم',
        'email.edit_template' => 'قالب ایمیل را تغییر دهم',
        'email.send_email' => 'ایمیل را ارسال کنم',
        'email.bulk_send' => 'ایمیل گروهی ارسال کنم',
        'blog.draft_post' => 'مطلب را آماده کنم',
        'blog.suggest_edit' => 'ویرایش مطلب را پیشنهاد کنم',
        'blog.publish_post' => 'مطلب را منتشر کنم',
        'blog.unpublish_post' => 'انتشار مطلب را متوقف کنم',
        'blog.delete_post' => 'مطلب را حذف کنم',
        'content.draft_faq_answer' => 'پاسخ پرسش متداول را آماده کنم',
        'content.draft_page_update' => 'ویرایش صفحه را آماده کنم',
        'content.publish_content' => 'محتوا را منتشر کنم',
        'content.delete_content' => 'محتوا را حذف کنم',
        'notifications.draft_announcement' => 'اطلاعیه را آماده کنم',
        'notifications.publish_announcement' => 'اطلاعیه را منتشر کنم',
        'notifications.change_global_notification_defaults' => 'تنظیمات عمومی اعلان‌ها را تغییر دهم',
        'invitations.summarize_growth' => 'روند رشد و دعوت‌ها را جمع‌بندی کنم',
        'invitations.recommend_request_decision' => 'برای درخواست دعوت پیشنهاد تصمیم آماده کنم',
        'invitations.issue_invitation' => 'دعوت‌نامه صادر کنم',
        'invitations.reject_invitation_request' => 'درخواست دعوت را رد کنم',
        'secretariat.draft_correspondence' => 'مکاتبه را آماده کنم',
        'secretariat.prepare_follow_up' => 'پیگیری مکاتبه را آماده کنم',
        'secretariat.register_formal_record' => 'سند رسمی را ثبت کنم',
        'secretariat.dispatch_formal_record' => 'سند رسمی را ارسال کنم',
        'secretariat.close_case' => 'پرونده دبیرخانه را ببندم',
        'secretariat.rewrite_history' => 'سوابق دبیرخانه را بازنویسی کنم',
        'stock.summarize_auction' => 'وضعیت حراج سهام را جمع‌بندی کنم',
        'stock.flag_settlement_issue' => 'مشکل تسویه سهام را علامت‌گذاری کنم',
        'stock.create_auction' => 'حراج سهام ایجاد کنم',
        'stock.settle_auction' => 'حراج سهام را تسویه کنم',
        'stock.transfer_shares' => 'سهام را منتقل کنم',
        'stock.alter_ownership_history' => 'سابقه مالکیت سهام را تغییر دهم',
        'najm_bahar.summarize_financial_state' => 'وضعیت مالی نجم بهار را جمع‌بندی کنم',
        'najm_bahar.flag_transaction_anomaly' => 'تراکنش مشکوک را علامت‌گذاری کنم',
        'najm_bahar.draft_project_review' => 'بررسی پروژه را آماده کنم',
        'najm_bahar.approve_project' => 'پروژه را تأیید کنم',
        'najm_bahar.execute_transaction' => 'تراکنش را اجرا کنم',
        'najm_bahar.change_monetary_policy' => 'سیاست پولی را تغییر دهم',
        'najm_bahar.alter_ledger_history' => 'سابقه دفترکل را تغییر دهم',
        'admin_settings.audit_configuration' => 'تنظیمات مدیریتی را ممیزی کنم',
        'admin_settings.recommend_change' => 'برای تنظیمات پیشنهاد تغییر آماده کنم',
        'admin_settings.change_setting' => 'تنظیم مدیریتی را تغییر دهم',
        'admin_settings.change_role_permission' => 'سطح دسترسی نقش را تغییر دهم',
        'admin_settings.disable_audit_controls' => 'کنترل‌های ممیزی را غیرفعال کنم',
        'runtime_health.collect_health_snapshot' => 'وضعیت سلامت سامانه را جمع‌آوری کنم',
        'runtime_health.classify_incident' => 'رخداد سامانه را دسته‌بندی کنم',
        'runtime_health.run_read_only_diagnostic' => 'عیب‌یابی خواندنی اجرا کنم',
        'runtime_health.restart_external_service' => 'سرویس بیرونی را راه‌اندازی مجدد کنم',
        'runtime_health.destroy_data' => 'داده سامانه را از بین ببرم',
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
                $base = $this->presentItem([
                    'domain' => $domainKey,
                    'action' => $action,
                    'mode' => $mode,
                    'state' => $state,
                ]);

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
                    $reasonCode = $this->blockedReason($state, $evidence);
                    $blocked[] = $this->presentItem($base + [
                        'reason_code' => $reasonCode,
                        'dependency' => $state === 'blocked_dependency'
                            ? (string) data_get($evidence, 'block.dependency', '')
                            : null,
                    ], $reasonCode);
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

            $needsFounderDecision[] = $this->presentItem([
                'domain' => $domain,
                'action' => $action,
                'mode' => 'approval_required',
                'state' => 'connected',
                'approval_request_id' => $raw['approval_request_id'] ?? null,
                'entity_type' => $raw['entity_type'] ?? null,
                'entity_id' => $raw['entity_id'] ?? null,
                'title' => $raw['title'] ?? null,
            ], null, is_string($raw['title'] ?? null) ? $raw['title'] : null);
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

    /** @param array<string,mixed> $item @return array<string,mixed> */
    protected function presentItem(array $item, ?string $blockedReason = null, ?string $preferredTitle = null): array
    {
        $domain = (string) ($item['domain'] ?? '');
        $action = (string) ($item['action'] ?? '');
        $mode = (string) ($item['mode'] ?? '');
        $key = $domain.'.'.$action;
        $title = $preferredTitle ?: (self::ACTION_TITLES[$key] ?? 'اقدام مرتبط این حوزه');
        $explanation = $blockedReason !== null
            ? $this->blockedExplanation($blockedReason)
            : $this->capabilityExplanation($key, $mode, (string) ($item['state'] ?? ''));

        $item['title'] = $title;
        $item['display_title'] = $title;
        $item['display_explanation'] = $explanation;
        if ($blockedReason !== null) {
            $item['reason'] = $explanation;
        }

        return $item;
    }

    protected function capabilityExplanation(string $key, string $mode, string $state): string
    {
        if ($key === 'users.draft_support_response') {
            return 'می‌توانم متن پاسخ مناسب برای درخواست پشتیبانی را آماده کنم؛ ارسال آن فقط از مسیر رسمی اختیار انجام می‌شود.';
        }

        if ($state !== 'connected') {
            return 'این قابلیت در سیاست تعریف شده، اما اتصال اجرایی قابل‌اثبات آن هنوز کامل نیست.';
        }

        return match ($mode) {
            'delegated_safe' => 'می‌توانم این کار را آماده کنم و فقط با واگذاری فعال، اجرای آن نیز مجاز می‌شود.',
            'propose' => 'می‌توانم تحلیل یا پیشنهاد لازم را آماده کنم، بدون اینکه تغییری را خودکار اعمال کنم.',
            'approval_required' => 'اجرای این کار فقط پس از تصمیم صریح شما و از lifecycle رسمی انجام می‌شود.',
            'observe' => 'می‌توانم این وضعیت را بررسی و گزارش کنم، اما تغییری اعمال نمی‌کنم.',
            default => 'این قابلیت فقط در حدود سیاست و اتصال ثبت‌شده قابل استفاده است.',
        };
    }

    protected function blockedExplanation(string $reason): string
    {
        return match ($reason) {
            'persisted_global_defaults_missing' => 'هنوز نمی‌توانم تنظیمات عمومی اعلان‌ها را تغییر دهم، چون زیرساخت ذخیره پایدار این تنظیمات به مسیر اجرایی متصل نشده است.',
            'real_transport_not_available' => 'ارسال واقعی هنوز به یک مسیر حمل‌ونقل قابل‌اثبات متصل نشده است؛ بنابراین فقط آماده‌سازی و ثبت داخلی ممکن است.',
            'forbidden_by_policy' => 'این اقدام طبق سیاست اختیار نجم هدا ممنوع است و از مسیر وزارت قابل اجرا نیست.',
            'canonical_connectivity_missing' => 'مسیر اجرایی canonical و قابل‌اثبات برای این اقدام هنوز متصل نشده است.',
            default => 'این قابلیت فعلاً به دلیل یک وابستگی اجرایی حل‌نشده قابل انجام نیست.',
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