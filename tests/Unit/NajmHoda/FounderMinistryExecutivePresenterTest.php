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
            'success'=>true,'message'=>'old','management'=>[
                'intent'=>'morning_brief','summary_cards'=>[],
                'global_summary_cards'=>['urgent'=>0,'founder_decisions'=>0,'prepared'=>0,'information'=>2],
                'items'=>[['priority'=>'P3','kind'=>'attention','title'=>'صرفاً اطلاع']],
            ],
        ],24);

        $this->assertStringContainsString('وضعیت مدیریتی EarthCoop',$result['message']);
        $this->assertStringContainsString('اقدام شما: فعلاً هیچ',$result['message']);
        $this->assertFalse(data_get($result,'management.executive.action_required'));
        $this->assertTrue(data_get($result,'management.executive.exception_driven'));
        $this->assertSame([],data_get($result,'management.items'));
    }

    public function test_morning_brief_keeps_only_exceptions_and_prepared_work(): void
    {
        $presenter=new FounderMinistryExecutivePresenter();
        $result=$presenter->present(['success'=>true,'management'=>[
            'intent'=>'morning_brief','summary_cards'=>[],
            'global_summary_cards'=>['urgent'=>1,'founder_decisions'=>0,'prepared'=>1,'information'=>4],
            'items'=>[
                ['priority'=>'P3','kind'=>'attention','title'=>'اطلاع'],
                ['priority'=>'P1','kind'=>'attention','title'=>'فوری'],
                ['priority'=>'P3','kind'=>'proposal','title'=>'پیش‌نویس'],
            ],
        ]],24);
        $this->assertCount(2,data_get($result,'management.items'));
        $this->assertSame('فوری',data_get($result,'management.items.0.title'));
        $this->assertSame('پیش‌نویس',data_get($result,'management.items.1.title'));
    }

    public function test_domain_brief_turns_metrics_into_an_executive_conclusion(): void
    {
        $presenter=new FounderMinistryExecutivePresenter();
        $result=$presenter->present(['success'=>true,'message'=>'old','management'=>[
            'intent'=>'governance','summary_cards'=>[
                'active'=>['label'=>'انتخابات فعال','value'=>1],
                'overdue'=>['label'=>'انتخابات عقب‌افتاده','value'=>0],
                'ending'=>['label'=>'پایان تا ۲۴ ساعت','value'=>0],
            ],
            'global_summary_cards'=>['urgent'=>0,'founder_decisions'=>0,'prepared'=>0,'information'=>0],
            'items'=>[],
        ]],24);
        $this->assertStringContainsString('انتخابات و حکمرانی — 24 ساعت اخیر',$result['message']);
        $this->assertStringContainsString('انتخابات فعال: 1',$result['message']);
        $this->assertStringContainsString('اقدام شما: هیچ',$result['message']);
        $this->assertSame('در این حوزه اقدام مدیریتی فوری لازم نیست',data_get($result,'management.executive.assessment'));
    }

    public function test_overdue_governance_is_marked_as_founder_follow_up(): void
    {
        $presenter=new FounderMinistryExecutivePresenter();
        $result=$presenter->present(['success'=>true,'management'=>[
            'intent'=>'governance','summary_cards'=>['overdue'=>['label'=>'انتخابات عقب‌افتاده','value'=>2]],
            'global_summary_cards'=>['urgent'=>0,'founder_decisions'=>0,'prepared'=>0,'information'=>0],
            'items'=>[],
        ]],24);
        $this->assertTrue(data_get($result,'management.executive.action_required'));
        $this->assertStringContainsString('انتخابات عقب‌افتاده',data_get($result,'management.executive.action_text'));
    }

    public function test_pending_decisions_are_explicitly_called_out(): void
    {
        $presenter=new FounderMinistryExecutivePresenter();
        $result=$presenter->present(['success'=>true,'message'=>'old','management'=>[
            'intent'=>'pending_approvals','summary_cards'=>['pending'=>3],
            'global_summary_cards'=>['urgent'=>0,'founder_decisions'=>3,'prepared'=>0,'information'=>0],
            'items'=>[['priority'=>'P1']],
        ]],24);
        $this->assertStringContainsString('3 تصمیم منتظر',$result['message']);
        $this->assertTrue(data_get($result,'management.executive.action_required'));
        $this->assertSame('نیازمند تصمیم یا پیگیری مدیرکل',data_get($result,'management.executive.assessment'));
    }

    public function test_authority_brief_explains_defined_actions_are_not_active_delegations(): void
    {
        $presenter=new FounderMinistryExecutivePresenter();
        $result=$presenter->present(['success'=>true,'management'=>[
            'intent'=>'authority','summary_cards'=>[
                'active_delegations'=>['label'=>'اختیار فعال','value'=>0],
                'total_actions'=>['label'=>'اقدام تعریف‌شده','value'=>84],
                'pending_approvals'=>['label'=>'تأیید منتظر','value'=>0],
                'overdue_approvals'=>['label'=>'تأیید عقب‌افتاده','value'=>0],
            ],
            'global_summary_cards'=>['urgent'=>0,'founder_decisions'=>0,'prepared'=>0,'information'=>0],
            'items'=>[],
        ]],24);
        $this->assertStringContainsString('84 اقدام در ماتریس اختیار تعریف شده است',$result['message']);
        $this->assertStringContainsString('این عدد به معنی واگذاری فعال نیست',$result['message']);
        $this->assertFalse(data_get($result,'management.executive.action_required'));
    }
}
