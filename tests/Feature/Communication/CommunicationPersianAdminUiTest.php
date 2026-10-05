<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommunicationPersianAdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_scheduled_smoke_template_is_seeded_without_required_context(): void
    {
        $template = CommunicationTemplate::query()
            ->where('key', 'system.scheduled_smoke_test')
            ->firstOrFail();

        $version = $template->versions()
            ->with('senderIdentity')
            ->where('locale', 'fa')
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $this->assertSame('operational', $template->classification->value ?? $template->classification);
        $this->assertSame([], $version->variables_schema);
        $this->assertSame('تست زمان‌بندی مرکز ارتباطات', $version->subject);
        $this->assertStringContainsString('تست سلامت زمان‌بندی و صف ارتباطات', $version->body);
        $this->assertSame('management', $version->senderIdentity?->key);
    }

    public function test_admin_communication_views_use_persian_user_facing_terms(): void
    {
        $createAutomation = file_get_contents(resource_path('views/admin/communications/automations/create.blade.php'));
        $indexAutomation = file_get_contents(resource_path('views/admin/communications/automations/index.blade.php'));
        $campaignCreate = file_get_contents(resource_path('views/admin/communications/campaigns/create.blade.php'));
        $campaignPreview = file_get_contents(resource_path('views/admin/communications/campaigns/preview.blade.php'));
        $senderCreate = file_get_contents(resource_path('views/admin/communications/senders/create.blade.php'));
        $senderEdit = file_get_contents(resource_path('views/admin/communications/senders/edit.blade.php'));
        $templateIndex = file_get_contents(resource_path('views/admin/communications/templates/index.blade.php'));
        $templateShow = file_get_contents(resource_path('views/admin/communications/templates/show.blade.php'));
        $dashboard = file_get_contents(resource_path('views/admin/communications/dashboard.blade.php'));
        $failures = file_get_contents(resource_path('views/admin/communications/failures.blade.php'));

        foreach ([
            'نوع اجرا', 'مخاطبان', 'شرط اجرا', 'رویدادمحور', 'زمان‌بندی‌شده', 'شرطی',
            'کاربر مشخص', 'الزامی', 'عملیاتی', 'اختیاری', 'هفتگی', 'روزانه', 'ساعتی',
            'فاصله اجرا', 'منطقه زمانی',
        ] as $term) {
            $this->assertStringContainsString($term, $createAutomation);
        }

        foreach (['رویدادمحور', 'زمان‌بندی‌شده', 'شرطی', 'کاربر مشخص', 'عضو', 'مدیر', 'بازرس'] as $term) {
            $this->assertStringContainsString($term, $indexAutomation);
        }

        foreach (['مخاطبان', 'کاربر مشخص', 'عملیاتی', 'اختیاری', 'الزامی'] as $term) {
            $this->assertStringContainsString($term, $campaignCreate);
        }

        foreach (['منطبق', 'مجاز به دریافت', 'عدم ارسال', 'نامعتبر'] as $term) {
            $this->assertStringContainsString($term, $campaignPreview);
        }

        foreach ([$senderCreate, $senderEdit] as $senderView) {
            $this->assertStringContainsString('نشانی پاسخ', $senderView);
            $this->assertStringContainsString('کلید هویت سیستمی', $senderView);
        }

        $this->assertStringContainsString('قالب‌های مرجع', $templateIndex);
        $this->assertStringContainsString('ساختار متغیرها', $templateShow);
        $this->assertStringContainsString('پردازشگر صف', $dashboard);
        $this->assertStringContainsString('تلاش مجدد', $failures);

        foreach ([
            'نوع Trigger', '>event</option>', '>scheduled</option>', '>conditional</option>',
            '>required</option>', '>operational</option>', '>optional</option>',
            '>weekly</option>', '>daily</option>', '>hourly</option>',
            '>Matched<', '>Eligible<', '>Suppressed<', '>Invalid<',
            'Reply-To', 'کلید System Identity', 'Schema متغیرها', 'قالب‌های canonical',
        ] as $forbidden) {
            $all = implode("\n", [
                $createAutomation, $indexAutomation, $campaignCreate, $campaignPreview,
                $senderCreate, $senderEdit, $templateIndex, $templateShow, $dashboard, $failures,
            ]);
            $this->assertStringNotContainsString($forbidden, $all);
        }
    }
}
