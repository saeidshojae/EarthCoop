<?php

namespace Tests\Unit\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderMinistryExecutivePresenter;
use PHPUnit\Framework\TestCase;

class FounderMinistryExecutivePresenterTest extends TestCase
{
    public function test_quiet_morning_brief_explicitly_says_no_founder_action_is_required(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present([
            'success' => true,
            'message' => 'old',
            'management' => [
                'intent' => 'morning_brief',
                'summary_cards' => [],
                'global_summary_cards' => ['urgent' => 0, 'founder_decisions' => 0, 'prepared' => 0, 'information' => 2],
                'items' => [['priority' => 'P3', 'kind' => 'attention', 'title' => 'صرفاً اطلاع']],
            ],
        ], 24);

        $this->assertStringContainsString('وضعیت مدیریتی EarthCoop', $result['message']);
        $this->assertStringContainsString('اقدام شما: فعلاً هیچ', $result['message']);
        $this->assertFalse(data_get($result, 'management.executive.action_required'));
        $this->assertTrue(data_get($result, 'management.executive.exception_driven'));
        $this->assertSame('global', data_get($result, 'management.executive.scope'));
        $this->assertSame([], data_get($result, 'management.items'));
    }

    public function test_morning_brief_keeps_only_exceptions_and_prepared_work(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present(['success' => true, 'management' => [
            'intent' => 'morning_brief',
            'summary_cards' => [],
            'global_summary_cards' => ['urgent' => 1, 'founder_decisions' => 0, 'prepared' => 1, 'information' => 4],
            'items' => [
                ['priority' => 'P3', 'kind' => 'attention', 'title' => 'اطلاع'],
                ['priority' => 'P1', 'kind' => 'attention', 'title' => 'فوری'],
                ['priority' => 'P3', 'kind' => 'proposal', 'title' => 'پیش‌نویس'],
            ],
        ]], 24);

        $this->assertCount(2, data_get($result, 'management.items'));
        $this->assertSame('فوری', data_get($result, 'management.items.0.title'));
        $this->assertSame('پیش‌نویس', data_get($result, 'management.items.1.title'));
    }

    public function test_quiet_domain_brief_is_concise_and_leaves_metrics_to_cards(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present(['success' => true, 'message' => 'old', 'management' => [
            'intent' => 'governance',
            'summary_cards' => [
                'active' => ['label' => 'انتخابات فعال', 'value' => 1],
                'overdue' => ['label' => 'انتخابات عقب‌افتاده', 'value' => 0],
                'ending' => ['label' => 'پایان تا ۲۴ ساعت', 'value' => 0],
            ],
            'global_summary_cards' => ['urgent' => 0, 'founder_decisions' => 0, 'prepared' => 0, 'information' => 0],
            'items' => [],
        ]], 24);

        $this->assertSame('انتخابات و حکمرانی در وضعیت فعلی نیازمند اقدام شما نیست. مورد کارت‌شده‌ای برای رسیدگی در این بخش وجود ندارد.', $result['message']);
        $this->assertStringNotContainsString('انتخابات فعال: 1', $result['message']);
        $this->assertSame(1, data_get($result, 'management.summary_cards.active.value'));
        $this->assertSame('در این حوزه اقدام مدیریتی فوری لازم نیست', data_get($result, 'management.executive.assessment'));
        $this->assertSame('intent', data_get($result, 'management.executive.scope'));
    }

    public function test_unrelated_global_urgency_does_not_contaminate_a_quiet_domain_brief(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present(['success' => true, 'management' => [
            'intent' => 'groups',
            'summary_cards' => [
                'total' => ['label' => 'کل گروه‌ها', 'value' => 81],
                'created' => ['label' => 'گروه جدید', 'value' => 0],
            ],
            'global_summary_cards' => ['urgent' => 5, 'founder_decisions' => 3, 'prepared' => 2, 'information' => 8],
            'items' => [],
        ]], 24);

        $this->assertFalse(data_get($result, 'management.executive.action_required'));
        $this->assertSame('در این حوزه اقدام مدیریتی فوری لازم نیست', data_get($result, 'management.executive.assessment'));
        $this->assertSame('فعلاً اقدامی از شما لازم نیست.', data_get($result, 'management.executive.action_text'));
        $this->assertStringContainsString('گروه‌ها در وضعیت فعلی نیازمند اقدام شما نیست', $result['message']);
        $this->assertStringNotContainsString('کل گروه‌ها: 81', $result['message']);
        $this->assertStringNotContainsString('5 مورد فوری', data_get($result, 'management.executive.action_text'));
    }

    public function test_overdue_governance_is_marked_as_founder_follow_up(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present(['success' => true, 'management' => [
            'intent' => 'governance',
            'summary_cards' => ['overdue' => ['label' => 'انتخابات عقب‌افتاده', 'value' => 2]],
            'global_summary_cards' => ['urgent' => 0, 'founder_decisions' => 0, 'prepared' => 0, 'information' => 0],
            'items' => [],
        ]], 24);

        $this->assertTrue(data_get($result, 'management.executive.action_required'));
        $this->assertStringContainsString('انتخابات عقب‌افتاده', data_get($result, 'management.executive.action_text'));
        $this->assertStringStartsWith('انتخابات و حکمرانی نیازمند پیگیری مدیرکل است', $result['message']);
    }

    public function test_unhealthy_runtime_is_consistently_marked_as_action_required(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present(['success' => true, 'management' => [
            'intent' => 'system_health',
            'summary_cards' => [
                'runtime_status' => 'degraded',
                'health_attention_items' => 0,
            ],
            'global_summary_cards' => ['urgent' => 0, 'founder_decisions' => 0, 'prepared' => 0, 'information' => 0],
            'items' => [],
        ]], 24);

        $this->assertTrue(data_get($result, 'management.executive.action_required'));
        $this->assertSame('سلامت سامانه نیازمند بررسی است', data_get($result, 'management.executive.assessment'));
        $this->assertSame('کارت‌های سلامت runtime و ریسک‌های مرتبط را بررسی کنید.', data_get($result, 'management.executive.action_text'));
        $this->assertStringContainsString('وضعیت runtime «degraded»', $result['message']);
    }

    public function test_pending_decisions_are_explicitly_called_out_without_being_relabelled_as_urgent(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present(['success' => true, 'message' => 'old', 'management' => [
            'intent' => 'pending_approvals',
            'summary_cards' => ['pending' => 3],
            'global_summary_cards' => ['urgent' => 0, 'founder_decisions' => 3, 'prepared' => 0, 'information' => 0],
            'items' => [
                ['priority' => 'P1', 'kind' => 'approval'],
                ['priority' => 'P1', 'kind' => 'approval'],
                ['priority' => 'P1', 'kind' => 'approval'],
            ],
        ]], 24);

        $this->assertStringContainsString('3 تصمیم منتظر', $result['message']);
        $this->assertTrue(data_get($result, 'management.executive.action_required'));
        $this->assertSame('نیازمند تصمیم یا پیگیری مدیرکل', data_get($result, 'management.executive.assessment'));
        $this->assertSame('درباره 3 تصمیم منتظر نظر بدهید.', data_get($result, 'management.executive.action_text'));
        $this->assertStringNotContainsString('مورد فوری', data_get($result, 'management.executive.action_text'));
    }

    public function test_authority_brief_explains_defined_actions_are_not_active_delegations(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present(['success' => true, 'management' => [
            'intent' => 'authority',
            'summary_cards' => [
                'active_delegations' => ['label' => 'اختیار فعال', 'value' => 0],
                'total_actions' => ['label' => 'اقدام تعریف‌شده', 'value' => 84],
                'pending_approvals' => ['label' => 'تأیید منتظر', 'value' => 0],
                'overdue_approvals' => ['label' => 'تأیید عقب‌افتاده', 'value' => 0],
            ],
            'global_summary_cards' => ['urgent' => 0, 'founder_decisions' => 0, 'prepared' => 0, 'information' => 0],
            'items' => [],
        ]], 24);

        $this->assertStringContainsString('84 اقدام در ماتریس اختیار تعریف شده است', $result['message']);
        $this->assertStringContainsString('این عدد به معنی واگذاری فعال نیست', $result['message']);
        $this->assertFalse(data_get($result, 'management.executive.action_required'));
    }

    public function test_presenter_preserves_backend_agency_snapshot_without_recalculating_authority(): void
    {
        $agency = [
            'scope' => 'intent',
            'domain_keys' => ['groups'],
            'connected' => true,
            'active_delegations' => [],
            'may_do_now' => [],
            'may_prepare' => [['domain' => 'groups', 'action' => 'summarize_activity']],
            'needs_founder_decision' => [],
            'blocked' => [],
            'summary' => 'یک قابلیت آماده‌سازی متصل است.',
        ];

        $result = (new FounderMinistryExecutivePresenter())->present([
            'success' => true,
            'management' => [
                'intent' => 'groups',
                'summary_cards' => [],
                'global_summary_cards' => [],
                'items' => [],
            ],
        ], 24, $agency);

        $this->assertSame($agency, data_get($result, 'management.executive.agency'));
    }
}
