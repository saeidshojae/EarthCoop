<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Models\FounderFinancialRiskFinding;
use App\Models\ModerationCaseSummary;
use App\Models\SupportReplyDraft;
use App\Modules\Secretariat\Models\SecretariatFollowUpProposal;
use Illuminate\Support\Collection;

class FounderExecutiveWorkQueueService
{
    public function __construct(
        protected FounderAttentionService $attention,
        protected FounderApprovalInboxService $approvals
    ) {}

    /** @return array<string,mixed> */
    public function snapshot(int $hours = 24, int $limit = 30): array
    {
        $hours = max(1, min($hours, 168));
        $limit = max(1, min($limit, 100));

        $brief = $this->attention->brief($hours);
        $approvalInbox = $this->approvals->snapshot(100);
        $items = collect();

        foreach ((array) ($approvalInbox['items'] ?? []) as $approval) {
            if (! is_array($approval)) continue;
            $overdue = (string) ($approval['sla_status'] ?? '') === 'overdue';
            $risk = (string) ($approval['risk'] ?? 'unknown');
            $priority = $overdue || in_array($risk, ['critical', 'high'], true) ? 'P0' : 'P1';
            $domain = (string) ($approval['domain'] ?? 'unknown');
            $action = (string) ($approval['domain_action'] ?? '');
            $items->push([
                'kind' => 'approval',
                'priority' => $priority,
                'domain' => $domain,
                'action' => $action,
                'entity_type' => data_get($approval, 'context.entity_type'),
                'entity_id' => data_get($approval, 'context.entity_id'),
                'title' => $this->approvalTitle($domain, $action, $overdue),
                'status' => (string) ($approval['sla_status'] ?? 'pending'),
                'requested_at' => $approval['requested_at'] ?? null,
                'deadline_at' => $approval['deadline_at'] ?? null,
                'approval_request_id' => $approval['id'] ?? null,
                'score' => $overdue ? 1000 : ($priority === 'P0' ? 900 : 800),
            ]);
        }

        foreach ((array) ($brief['items'] ?? []) as $attention) {
            if (! is_array($attention)) continue;
            $priority = (string) ($attention['priority'] ?? 'P3');
            $items->push([
                'kind' => 'attention',
                'priority' => $priority,
                'domain' => (string) ($attention['domain'] ?? 'unknown'),
                'action' => null,
                'entity_type' => data_get($attention, 'context.entity_type'),
                'entity_id' => data_get($attention, 'context.entity_id'),
                'title' => (string) ($attention['title'] ?? 'مورد نیازمند توجه'),
                'status' => 'attention',
                'requested_at' => data_get($attention, 'context.created_at'),
                'deadline_at' => null,
                'score' => $this->priorityScore($priority),
            ]);
        }

        $this->appendDraftItems($items);

        $items = $items
            ->sortByDesc(static fn (array $item): int => (int) ($item['score'] ?? 0))
            ->take($limit)
            ->values();

        $counts = $items->countBy(static fn (array $item): string => (string) ($item['priority'] ?? 'P3'))->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $hours,
            'total' => $items->count(),
            'needs_founder_decision' => $items->where('kind', 'approval')->count(),
            'prepared_by_najm_hoda' => $items->where('kind', 'proposal')->count(),
            'attention_only' => $items->where('kind', 'attention')->count(),
            'by_priority' => [
                'P0' => (int) ($counts['P0'] ?? 0),
                'P1' => (int) ($counts['P1'] ?? 0),
                'P2' => (int) ($counts['P2'] ?? 0),
                'P3' => (int) ($counts['P3'] ?? 0),
            ],
            'items' => $items->map(static function (array $item): array {
                unset($item['score']);
                return $item;
            })->all(),
        ];
    }

    protected function appendDraftItems(Collection $items): void
    {
        foreach (SupportReplyDraft::query()->where('status', 'draft')->latest('id')->limit(10)->get() as $draft) {
            $items->push($this->proposal('support', 'send_reply', 'support_reply_draft', (int) $draft->id, 'پاسخ پشتیبانی آماده بررسی است', $draft->created_at?->toIso8601String()));
        }
        foreach (ModerationCaseSummary::query()->where('status', 'draft')->latest('id')->limit(10)->get() as $case) {
            $priority = (string) $case->severity === 'high' ? 'P1' : 'P2';
            $items->push($this->proposal('reports_moderation', 'resolve_report', 'moderation_case_summary', (int) $case->id, 'پرونده نظارتی آماده بررسی است', $case->created_at?->toIso8601String(), $priority));
        }
        foreach (SecretariatFollowUpProposal::query()->where('status', 'draft')->latest('id')->limit(10)->get() as $proposal) {
            $priority = (string) $proposal->urgency === 'high' ? 'P1' : 'P2';
            $items->push($this->proposal('secretariat', 'prepare_follow_up', 'secretariat_follow_up_proposal', (int) $proposal->id, 'پیشنهاد پیگیری دبیرخانه آماده است', $proposal->created_at?->toIso8601String(), $priority));
        }
        foreach (FounderEmailDraft::query()->where('status', 'draft')->latest('id')->limit(10)->get() as $draft) {
            $items->push($this->proposal('email', 'send_email', 'founder_email_draft', (int) $draft->id, 'پیش‌نویس ایمیل آماده بررسی است', $draft->created_at?->toIso8601String()));
        }
        foreach (FounderContentDraft::query()->where('status', 'draft')->latest('id')->limit(10)->get() as $draft) {
            $items->push($this->proposal('blog', 'publish_post', 'founder_content_draft', (int) $draft->id, 'پیش‌نویس محتوا آماده بررسی است', $draft->created_at?->toIso8601String()));
        }
        foreach (FounderAnnouncementDraft::query()->where('status', 'draft')->latest('id')->limit(10)->get() as $draft) {
            $items->push($this->proposal('notifications', 'publish_announcement', 'founder_announcement_draft', (int) $draft->id, 'پیش‌نویس اطلاعیه آماده بررسی است', $draft->created_at?->toIso8601String()));
        }
        foreach (FounderFinancialRiskFinding::query()->where('status', 'open')->latest('id')->limit(10)->get() as $finding) {
            $severity = (string) $finding->severity;
            $priority = in_array($severity, ['critical', 'high'], true) ? 'P0' : 'P1';
            $items->push([
                'kind'=>'attention','priority'=>$priority,'domain'=>'najm_bahar','action'=>null,
                'entity_type'=>'founder_financial_risk_finding','entity_id'=>(int)$finding->id,
                'title'=>'یک هشدار باز در سلامت مالی نیازمند بررسی است','status'=>'open','requested_at'=>$finding->created_at?->toIso8601String(),
                'deadline_at'=>null,'score'=>$this->priorityScore($priority) + ($severity === 'critical' ? 50 : 0),
            ]);
        }
    }

    protected function approvalTitle(string $domain, string $action, bool $overdue): string
    {
        $domainLabel = match ($domain) {
            'support' => 'پاسخ پشتیبانی',
            'reference_data', 'locations' => 'داده پایه',
            'reports_moderation' => 'پرونده نظارتی',
            'email' => 'ایمیل',
            'blog' => 'محتوا',
            'notifications' => 'اطلاعیه',
            'secretariat' => 'دبیرخانه',
            'najm_bahar' => 'نجم بهار',
            'stock' => 'سهام و تأمین مالی',
            'governance' => 'حکمرانی و انتخابات',
            'admin_settings' => 'تنظیمات مدیریتی',
            default => 'اقدام مدیریتی',
        };

        $actionLabel = match ($action) {
            'send_reply' => 'ارسال پاسخ',
            'approve' => 'تأیید',
            'resolve_report' => 'رسیدگی نهایی',
            'send_email' => 'ارسال',
            'publish_post', 'publish_announcement' => 'انتشار',
            'register_formal_record' => 'ثبت رسمی',
            'close_case' => 'بستن پرونده',
            default => 'تصمیم',
        };

        return ($overdue ? 'عقب‌افتاده: ' : '') . $domainLabel . ' — ' . $actionLabel . ' منتظر تصمیم شماست';
    }

    /** @return array<string,mixed> */
    protected function proposal(string $domain, string $action, string $entityType, int $entityId, string $title, ?string $createdAt, string $priority = 'P2'): array
    {
        return [
            'kind'=>'proposal','priority'=>$priority,'domain'=>$domain,'action'=>$action,
            'entity_type'=>$entityType,'entity_id'=>$entityId,'title'=>$title,'status'=>'prepared',
            'requested_at'=>$createdAt,'deadline_at'=>null,'score'=>$this->priorityScore($priority) + 25,
        ];
    }

    protected function priorityScore(string $priority): int
    {
        return match ($priority) {
            'P0' => 700,
            'P1' => 500,
            'P2' => 300,
            default => 100,
        };
    }
}
