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
                'global_summary_cards' => [
                    'urgent' => 0,
                    'founder_decisions' => 0,
                    'prepared' => 0,
                    'information' => 2,
                ],
                'items' => [],
            ],
        ], 24);

        $this->assertStringContainsString('وضعیت مدیریتی EarthCoop', $result['message']);
        $this->assertStringContainsString('اقدام شما: فعلاً هیچ', $result['message']);
        $this->assertFalse(data_get($result, 'management.executive.action_required'));
        $this->assertTrue(data_get($result, 'management.executive.exception_driven'));
    }

    public function test_domain_brief_turns_metrics_into_an_executive_conclusion(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present([
            'success' => true,
            'message' => 'old',
            'management' => [
                'intent' => 'governance',
                'summary_cards' => [
                    'active' => ['label' => 'انتخابات فعال', 'value' => 1],
                    'overdue' => ['label' => 'انتخابات عقب‌افتاده', 'value' => 0],
                    'ending' => ['label' => 'پایان تا ۲۴ ساعت', 'value' => 0],
                ],
                'global_summary_cards' => [
                    'urgent' => 0,
                    'founder_decisions' => 0,
                    'prepared' => 0,
                    'information' => 0,
                ],
                'items' => [],
            ],
        ], 24);

        $this->assertStringContainsString('انتخابات و حکمرانی — 24 ساعت اخیر', $result['message']);
        $this->assertStringContainsString('انتخابات فعال: 1', $result['message']);
        $this->assertStringContainsString('اقدام شما: هیچ', $result['message']);
        $this->assertSame('در این حوزه اقدام مدیریتی فوری لازم نیست', data_get($result, 'management.executive.assessment'));
    }

    public function test_pending_decisions_are_explicitly_called_out(): void
    {
        $presenter = new FounderMinistryExecutivePresenter();
        $result = $presenter->present([
            'success' => true,
            'message' => 'old',
            'management' => [
                'intent' => 'pending_approvals',
                'summary_cards' => ['pending' => 3],
                'global_summary_cards' => [
                    'urgent' => 0,
                    'founder_decisions' => 3,
                    'prepared' => 0,
                    'information' => 0,
                ],
                'items' => [['priority' => 'P1']],
            ],
        ], 24);

        $this->assertStringContainsString('3 تصمیم منتظر', $result['message']);
        $this->assertTrue(data_get($result, 'management.executive.action_required'));
        $this->assertSame('نیازمند تصمیم مدیرکل', data_get($result, 'management.executive.assessment'));
    }
}
