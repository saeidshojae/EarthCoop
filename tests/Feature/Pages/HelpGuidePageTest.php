<?php

namespace Tests\Feature\Pages;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpGuidePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_guide_uses_a_short_intro_and_three_accessible_accordion_sections(): void
    {
        Page::create([
            'title' => 'راهنمای استفاده',
            'title_translations' => [
                'fa' => 'راهنمای استفاده',
                'en' => 'User Guide',
                'ar' => 'دليل الاستخدام',
            ],
            'slug' => 'help-guide-accordion-test',
            'template' => 'help',
            'content' => '<p>به راهنمای EarthCoop خوش آمدید.</p>',
            'content_translations' => [
                'fa' => '<p>به راهنمای EarthCoop خوش آمدید.</p>',
                'en' => '<p>Welcome to the EarthCoop user guide.</p>',
                'ar' => '<p>مرحباً بكم في دليل استخدام EarthCoop.</p>',
            ],
            'meta_title' => 'راهنمای استفاده',
            'meta_description' => 'راهنمای استفاده از EarthCoop',
            'meta_description_translations' => [
                'fa' => 'راهنمای استفاده از EarthCoop',
                'en' => 'EarthCoop user guide',
                'ar' => 'دليل استخدام EarthCoop',
            ],
            'is_published' => true,
            'show_in_header' => false,
        ]);

        $response = $this->get('/pages/help-guide-accordion-test');

        $response->assertOk()
            ->assertSee('به راهنمای EarthCoop خوش آمدید.')
            ->assertSee('پذیرش شرایط و آغاز ثبت‌نام')
            ->assertSee('مراحل ثبت‌نام در EarthCoop')
            ->assertSee('خانهٔ شما در EarthCoop')
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('aria-controls="content-1"', false)
            ->assertSee('aria-controls="content-2"', false)
            ->assertSee('aria-controls="content-3"', false)
            ->assertSee('حساب نجم بهار')
            ->assertSee('اجتماع محلی با حکمرانی رسمی یکسان نیست');

        $response->assertDontSee('۱۰ کد دعوت')
            ->assertDontSee('۱۰ بهار')
            ->assertDontSee('۲۰ نفر')
            ->assertDontSee('هر سه ماه یک‌بار')
            ->assertDontSee('تا سطح محله الزامی است');
    }
}
