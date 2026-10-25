<?php

namespace Tests\Feature\Pages;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTemplateLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_template_copy_is_localized_in_english_and_arabic(): void
    {
        Page::create([
            'title' => 'درباره آزمایشی',
            'title_translations' => [
                'fa' => 'درباره آزمایشی',
                'en' => 'About EarthCoop',
                'ar' => 'حول EarthCoop',
            ],
            'slug' => 'about-template-localization-test',
            'template' => 'about',
            'content' => '<p>محتوای آزمایشی</p>',
            'content_translations' => [
                'fa' => '<p>محتوای آزمایشی</p>',
                'en' => '<p>Translated body content</p>',
                'ar' => '<p>محتوى تجريبي مترجم</p>',
            ],
            'meta_title' => 'About test',
            'meta_description' => 'About test description',
            'meta_description_translations' => [
                'fa' => 'توضیحات آزمایشی',
                'en' => 'About EarthCoop, a global partnership network',
                'ar' => 'حول EarthCoop، شبكة شراكة عالمية',
            ],
            'is_published' => true,
            'show_in_header' => false,
        ]);

        $cases = [
            'en' => [
                'The Earth is our shared home.',
                'What is EarthCoop?',
                'Professional and specialist fields',
                'Together, let’s build a different future',
            ],
            'ar' => [
                'الأرض بيتنا المشترك جميعًا.',
                'ما هي EarthCoop؟',
                'المجالات المهنية والتخصصية',
                'معًا، لنبنِ مستقبلًا مختلفًا',
            ],
        ];

        foreach ($cases as $locale => $expectedTexts) {
            $response = $this->withSession(['locale' => $locale])
                ->get('/pages/about-template-localization-test');

            $response->assertOk();
            foreach ($expectedTexts as $text) {
                $response->assertSee($text);
            }
            $response->assertDontSee('زمین، خانه مشترک همه‌ی ماست.');
            $response->assertDontSee('دعوت به عضویت');
        }
    }

    public function test_cooperation_page_template_copy_is_localized_in_english_and_arabic(): void
    {
        Page::create([
            'title' => 'همکاری آزمایشی',
            'title_translations' => [
                'fa' => 'همکاری آزمایشی',
                'en' => 'Cooperation',
                'ar' => 'التعاون',
            ],
            'slug' => 'cooperation-template-localization-test',
            'template' => 'cooperation',
            'content' => '<p>محتوای آزمایشی</p>',
            'content_translations' => [
                'fa' => '<p>محتوای آزمایشی</p>',
                'en' => '<p>Translated cooperation body</p>',
                'ar' => '<p>محتوى تعاون مترجم</p>',
            ],
            'meta_title' => 'Cooperation test',
            'meta_description' => 'Cooperation test description',
            'meta_description_translations' => [
                'fa' => 'توضیحات آزمایشی',
                'en' => 'Cooperation opportunities with EarthCoop',
                'ar' => 'فرص التعاون مع EarthCoop',
            ],
            'is_published' => true,
            'show_in_header' => false,
        ]);

        $cases = [
            'en' => [
                'Graphic and UI designer',
                'Android and iOS app developer',
                'AI assistant developer',
                'Infographic and motion-graphics creator',
                'Email copied:',
            ],
            'ar' => [
                'مصمم جرافيك وواجهات مستخدم',
                'مطوّر تطبيقات Android وiOS',
                'مطوّر مساعدات الذكاء الاصطناعي',
                'مصمم إنفوغرافيك ورسوم متحركة',
                'تم نسخ البريد الإلكتروني:',
            ],
        ];

        foreach ($cases as $locale => $expectedTexts) {
            $response = $this->withSession(['locale' => $locale])
                ->get('/pages/cooperation-template-localization-test');

            $response->assertOk();
            foreach ($expectedTexts as $text) {
                $response->assertSee($text);
            }
            $response->assertDontSee('گرافیست و طراح رابط کاربری');
            $response->assertDontSee('ایمیل کپی شد:');
        }
    }
}
