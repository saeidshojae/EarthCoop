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
            'content' => '<p>متن قدیمی نباید نمایش داده شود.</p>',
            'content_translations' => [
                'fa' => '<p>متن قدیمی نباید نمایش داده شود.</p>',
                'en' => '<p>Old introduction must not appear.</p>',
                'ar' => '<p>يجب ألا تظهر المقدمة القديمة.</p>',
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
            ->assertSee('در این راهنما، مسیر ثبت‌نام، تکمیل اطلاعات و استفاده از گروه‌ها و امکانات حکمرانی EarthCoop را در سه بخش زیر دنبال کنید.')
            ->assertDontSee('متن قدیمی نباید نمایش داده شود.')
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
    public function test_help_guide_accordion_copy_is_localized_in_english_and_arabic(): void
    {
        Page::create([
            'title' => 'راهنمای استفاده',
            'title_translations' => [
                'fa' => 'راهنمای استفاده',
                'en' => 'User Guide',
                'ar' => 'دليل الاستخدام',
            ],
            'slug' => 'help-guide-localization-test',
            'template' => 'help',
            'content' => '<p>مقدمه فارسی</p>',
            'content_translations' => [
                'fa' => '<p>مقدمه فارسی</p>',
                'en' => '<p>English introduction</p>',
                'ar' => '<p>مقدمة عربية</p>',
            ],
            'meta_title' => 'User guide',
            'meta_description' => 'User guide',
            'meta_description_translations' => [
                'fa' => 'راهنمای استفاده',
                'en' => 'User guide',
                'ar' => 'دليل الاستخدام',
            ],
            'is_published' => true,
            'show_in_header' => false,
        ]);

        $cases = [
            'en' => [
                'Use the three sections below to learn how to register, complete your profile, and take part in EarthCoop groups and governance.',
                'Old introduction must not appear.',
                'Accept the Terms and Start Registration',
                'Registration Steps on EarthCoop',
                'Your Home on EarthCoop',
                'Invitation code and account creation',
                'My location and governance',
                'A local community is not the same as formal governance.',
                'Group-chat messaging requires a Najm Bahar account',
            ],
            'ar' => [
                'استخدموا الأقسام الثلاثة أدناه للتعرّف على التسجيل واستكمال الملف الشخصي والمشاركة في مجموعات EarthCoop وميزات الحوكمة.',
                'يجب ألا تظهر المقدمة العربية القديمة.',
                'الموافقة على الشروط وبدء التسجيل',
                'خطوات التسجيل في EarthCoop',
                'مساحتك على EarthCoop',
                'رمز الدعوة وإنشاء الحساب',
                'موقعي وحوكمتي',
                'المجتمع المحلي ليس هو الحوكمة الرسمية.',
                'يتطلب إرسال الرسائل في الدردشة الجماعية امتلاك حساب نجم بهار',
            ],
        ];

        foreach ($cases as $locale => $expectedTexts) {
            $response = $this->withSession(['locale' => $locale])
                ->get('/pages/help-guide-localization-test');

            $response->assertOk();
            foreach ($expectedTexts as $text) {
                $response->assertSee($text);
            }
            $response->assertDontSee('پذیرش شرایط و آغاز ثبت‌نام');
            $response->assertDontSee('اجتماع محلی با حکمرانی رسمی یکسان نیست');
            $response->assertDontSee('کد دعوت و ایجاد حساب');
        }
    }

}
