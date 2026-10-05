<?php

namespace Tests\Unit\Communication;

use App\Support\Communication\CommunicationAdminLabels;
use PHPUnit\Framework\TestCase;

final class CommunicationAdminLabelsTest extends TestCase
{
    public function test_user_facing_communication_values_have_persian_labels(): void
    {
        $this->assertSame('رویدادمحور', CommunicationAdminLabels::trigger('event'));
        $this->assertSame('زمان‌بندی‌شده', CommunicationAdminLabels::trigger('scheduled'));
        $this->assertSame('شرطی', CommunicationAdminLabels::trigger('conditional'));

        $this->assertSame('کاربر مشخص', CommunicationAdminLabels::audience('specific.user'));
        $this->assertSame('اعضا', CommunicationAdminLabels::audience('role.member'));
        $this->assertSame('مدیران', CommunicationAdminLabels::audience('role.manager'));
        $this->assertSame('بازرسان', CommunicationAdminLabels::audience('role.inspector'));

        $this->assertSame('تکمیل ثبت‌نام', CommunicationAdminLabels::condition('user.registration_complete'));
        $this->assertSame('تأیید ایمیل', CommunicationAdminLabels::condition('user.email_verified'));

        $this->assertSame('الزامی', CommunicationAdminLabels::classification('required'));
        $this->assertSame('عملیاتی', CommunicationAdminLabels::classification('operational'));
        $this->assertSame('اختیاری', CommunicationAdminLabels::classification('optional'));

        $this->assertSame('ساعتی', CommunicationAdminLabels::frequency('hourly'));
        $this->assertSame('روزانه', CommunicationAdminLabels::frequency('daily'));
        $this->assertSame('هفتگی', CommunicationAdminLabels::frequency('weekly'));

        $this->assertSame('در صف', CommunicationAdminLabels::deliveryStatus('queued'));
        $this->assertSame('ارسال‌شده', CommunicationAdminLabels::deliveryStatus('sent'));
        $this->assertSame('در حال تلاش مجدد', CommunicationAdminLabels::deliveryStatus('retrying'));
        $this->assertSame('عدم ارسال', CommunicationAdminLabels::deliveryStatus('suppressed'));

        $this->assertSame('پیش‌نویس', CommunicationAdminLabels::campaignStatus('draft'));
        $this->assertSame('زمان‌بندی‌شده', CommunicationAdminLabels::campaignStatus('scheduled'));
        $this->assertSame('در حال ارسال', CommunicationAdminLabels::campaignStatus('running'));
        $this->assertSame('متوقف موقت', CommunicationAdminLabels::campaignStatus('paused'));

        $this->assertSame('فارسی', CommunicationAdminLabels::locale('fa'));
        $this->assertSame('موقت و قابل تلاش مجدد', CommunicationAdminLabels::failureClass('transient'));
        $this->assertSame('دائمی', CommunicationAdminLabels::failureClass('permanent'));
    }

    public function test_unknown_technical_values_remain_visible_for_diagnostics(): void
    {
        $this->assertSame('custom.status', CommunicationAdminLabels::deliveryStatus('custom.status'));
        $this->assertSame('custom.audience', CommunicationAdminLabels::audience('custom.audience'));
    }
}
