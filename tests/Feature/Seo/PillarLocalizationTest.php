<?php

namespace Tests\Feature\Seo;

use Tests\TestCase;

class PillarLocalizationTest extends TestCase
{
    /** @dataProvider localizedPillarProvider */
    public function test_core_pillar_pages_render_complete_english_and_arabic_copy(
        string $locale,
        string $path,
        string $expectedTitle,
        string $expectedSection,
        string $expectedRelatedLabel,
    ): void {
        $html = $this->withSession(['locale' => $locale])->get($path)->assertOk()->getContent();

        $this->assertStringContainsString($expectedTitle, $html);
        $this->assertStringContainsString($expectedSection, $html);
        $this->assertStringContainsString($expectedRelatedLabel, $html);
        $this->assertStringContainsString('dir="' . ($locale === 'en' ? 'ltr' : 'rtl') . '"', $html);
        $this->assertStringContainsString('inLanguage&quot;:&quot;' . $locale, $html);
        $this->assertStringContainsString('content="index,follow"', $html);
        $this->assertStringNotContainsString('مدل اقتصادی EarthCoop', $html);
    }

    public static function localizedPillarProvider(): array
    {
        return [
            'economy English' => ['en', '/economy', 'What Is the People’s Free Economy?', 'Economy in the service of justice', 'Glass Economy: transparent and secure'],
            'economy Arabic' => ['ar', '/economy', 'ما الاقتصاد الحرّ الشعبي؟', 'الاقتصاد في خدمة العدالة', 'الاقتصاد الزجاجي: الشفافية والأمان'],
            'cooperative English' => ['en', '/cooperative', 'Modern Cooperation:', 'Cooperation is more than temporary collaboration', 'Global cooperation'],
            'cooperative Arabic' => ['ar', '/cooperative', 'التعاون الحديث:', 'التعاون أكثر من عمل مشترك مؤقت', 'التعاون العالمي'],
            'governance English' => ['en', '/governance', 'Democratic and Participatory Governance', 'A chain of participation, oversight and accountability', 'Continuous elections without candidates'],
            'governance Arabic' => ['ar', '/governance', 'الحوكمة الديمقراطية والتشاركية', 'سلسلة المشاركة والرقابة والمساءلة', 'انتخابات مستمرة بلا مرشحين'],
            'justice English' => ['en', '/justice', 'Justice, Rights, Freedom and Equality', 'The Earth in its proper place', 'The Earth and common resources'],
            'justice Arabic' => ['ar', '/justice', 'العدالة والحقوق والحرية والمساواة', 'الأرض في موضعها الصحيح', 'الأرض والموارد المشتركة'],
        ];
    }
}
