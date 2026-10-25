<?php

namespace Tests\\Feature\\Seo;

use Tests\\TestCase;

class PillarFooterLinksTest extends TestCase
{
    /**
     * The shared footer must expose all four pillar pages on both the welcome
     * footer and the standard site footer, regardless of the selected locale.
     *
     * @dataProvider footerLocaleAndPageProvider
     */
    public function test_all_pillar_links_are_present_in_every_footer_locale(string $locale, string $path): void
    {
        $response = $this->withSession(['locale' => $locale])->get($path)->assertOk();
        $html = $response->getContent();

        foreach ([
            'seo.pillars.cooperative',
            'seo.pillars.economy',
            'seo.pillars.governance',
            'seo.pillars.justice',
        ] as $routeName) {
            $response->assertSee(route($routeName), false);
        }

        $expectedLabels = [
            'fa' => ['تعاون نوین', 'اقتصاد آزاد مردمی', 'حکمرانی مشارکتی', 'عدالت و حقوق بنیادین'],
            'en' => ['Modern Cooperation', 'People’s Free Economy', 'Participatory Governance', 'Justice and Fundamental Rights'],
            'ar' => ['التعاون الحديث', 'الاقتصاد الحرّ الشعبي', 'الحوكمة التشاركية', 'العدالة والحقوق الأساسية'],
        ];

        foreach ($expectedLabels[$locale] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public static function footerLocaleAndPageProvider(): array
    {
        return [
            'Persian welcome footer' => ['fa', '/'],
            'English welcome footer' => ['en', '/'],
            'Arabic welcome footer' => ['ar', '/'],
            'Persian standard footer' => ['fa', '/privacy'],
            'English standard footer' => ['en', '/privacy'],
            'Arabic standard footer' => ['ar', '/privacy'],
        ];
    }

    /**
     * @dataProvider privacyDirectionProvider
     */
    public function test_privacy_copy_has_explicit_directional_text_alignment(string $locale, string $direction, string $alignment): void
    {
        $html = $this->withSession(['locale' => $locale])->get('/privacy')->assertOk()->getContent();

        $this->assertStringContainsString('dir="' . $direction . '"', $html);
        $this->assertStringContainsString('style="text-align: ' . $alignment . ';"', $html);
    }

    public static function privacyDirectionProvider(): array
    {
        return [
            'Persian right aligned' => ['fa', 'rtl', 'right'],
            'Arabic right aligned' => ['ar', 'rtl', 'right'],
            'English left aligned' => ['en', 'ltr', 'left'],
        ];
    }
}
