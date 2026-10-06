<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
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

    public function test_builtin_sender_metadata_is_persian_for_admin_forms(): void
    {
        $support = CommunicationSenderIdentity::query()->where('key', 'support')->firstOrFail();
        $management = CommunicationSenderIdentity::query()->where('key', 'management')->firstOrFail();

        $this->assertSame('تیم پشتیبانی EarthCoop', $support->display_name);
        $this->assertSame('ارتباطات پشتیبانی و خدمات', $support->purpose);
        $this->assertSame('مدیریت EarthCoop', $management->display_name);
        $this->assertSame('ارتباطات مدیریتی و اداری', $management->purpose);
    }

    public function test_builtin_template_and_rule_names_are_persian(): void
    {
        $expected = [
            'onboarding.welcome' => 'خوش‌آمدگویی پس از ثبت‌نام',
            'reports.member.weekly' => 'گزارش هفتگی اعضا',
            'reports.manager.weekly' => 'گزارش هفتگی مدیران',
            'reports.inspector.weekly' => 'گزارش هفتگی بازرسان',
        ];

        foreach ($expected as $key => $name) {
            $template = CommunicationTemplate::query()->where('key', $key)->firstOrFail();
            $this->assertSame($name, $template->name);

            $rule = \App\Models\CommunicationRule::query()->where('key', $key.'.rule')->firstOrFail();
            $this->assertSame($name, $rule->name);
        }
    }

    public function test_admin_communication_views_use_persian_user_facing_terms(): void
    {
        $sources = $this->viewSources();
        $createAutomation = $sources['automations/create.blade.php'];
        $indexAutomation = $sources['automations/index.blade.php'];
        $campaignCreate = $sources['campaigns/create.blade.php'];
        $campaignIndex = $sources['campaigns/index.blade.php'];
        $campaignPreview = $sources['campaigns/preview.blade.php'];
        $senderCreate = $sources['senders/create.blade.php'];
        $senderEdit = $sources['senders/edit.blade.php'];
        $templateIndex = $sources['templates/index.blade.php'];
        $templateShow = $sources['templates/show.blade.php'];
        $dashboard = $sources['dashboard.blade.php'];
        $history = $sources['history.blade.php'];
        $failures = $sources['failures.blade.php'];

        foreach (['نوع اجرا', 'مخاطبان', 'شرط اجرا', 'کاربر مشخص', 'فاصله اجرا', 'منطقه زمانی'] as $term) {
            $this->assertStringContainsString($term, $createAutomation);
        }
        foreach (['trigger($type)', 'audience($key)', 'condition($key)', 'classification($classification)', 'frequency($frequency)'] as $helperCall) {
            $this->assertStringContainsString($helperCall, $createAutomation);
        }

        foreach (['trigger($rule->trigger_type)', 'audience($audienceKey)', 'frequency($rule->schedule->frequency)', "?->timezone(\$rule->schedule->timezone ?: config('"] as $helperCall) {
            $this->assertStringContainsString($helperCall, $indexAutomation);
        }
        $this->assertStringContainsString('campaignStatus($campaign->status)', $campaignIndex);

        $this->assertStringContainsString('مخاطبان', $campaignCreate);
        $this->assertStringContainsString('کاربر مشخص', $campaignCreate);
        $this->assertStringContainsString('classification($classification)', $campaignCreate);
        $this->assertStringContainsString('وضعیت کمپین', $campaignIndex);

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
        $this->assertStringContainsString('وضعیت تحویل', $history);
        $this->assertStringContainsString('deliveryStatus(', $history);
        $this->assertStringContainsString('تلاش مجدد', $failures);
        $this->assertStringContainsString('failureClass(', $failures);

        $all = implode("\n", $sources);
        foreach ([
            'نوع Trigger', '>event</option>', '>scheduled</option>', '>conditional</option>',
            '>required</option>', '>operational</option>', '>optional</option>',
            '>weekly</option>', '>daily</option>', '>hourly</option>',
            '>Matched<', '>Eligible<', '>Suppressed<', '>Invalid<',
            'Reply-To', 'کلید System Identity', 'Schema متغیرها', 'قالب‌های canonical',
            'Audience</label>', 'Condition</label>', 'Event key</label>',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $all);
        }
    }

    /** @return array<string,string> */
    private function viewSources(): array
    {
        $root = resource_path('views/admin/communications');
        $sources = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $sources[$relative] = (string) file_get_contents($file->getPathname());
        }

        ksort($sources);
        $this->assertCount(13, $sources, 'تمام ویوهای فعلی مرکز ارتباطات باید زیر قرارداد فارسی‌سازی باشند.');

        return $sources;
    }
}
