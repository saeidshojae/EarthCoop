<?php

namespace Tests\Feature\Seo;

use Tests\TestCase;

class PillarPagesTest extends TestCase
{
    /** @dataProvider pillarProvider */
    public function test_pillar_pages_are_indexable_canonical_and_structured(
        string $path,
        string $expectedTitleFragment,
        string $docsPath,
        string $relatedPath,
    ): void {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<h1\b[^>]*>/iu', $html));
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
        $this->assertStringContainsString('href="https://earthcoop.ir'.$path.'"', $html);
        $this->assertStringContainsString('content="index,follow"', $html);
        $this->assertStringContainsString($expectedTitleFragment, $html);
        $this->assertStringContainsString('href="'.$docsPath.'"', $html);
        $this->assertStringContainsString('href="'.$relatedPath.'"', $html);

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertCount(2, $matches[1]);
        $types = array_map(
            fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR)['@type'],
            $matches[1],
        );
        $this->assertSame(['WebPage', 'BreadcrumbList'], $types);
    }

    public static function pillarProvider(): array
    {
        return [
            'people free economy' => [
                '/economy',
                'اقتصاد آزاد مردمی',
                'https://docs.earthcoop.ir/documents/econ/',
                '/economy/glass',
            ],
            'glass economy' => [
                '/economy/glass',
                'اقتصاد شیشه‌ای',
                'https://docs.earthcoop.ir/documents/econ/',
                '/economy',
            ],
            'participatory governance' => [
                '/governance',
                'حکمرانی دموکراتیک و مشارکتی',
                'https://docs.earthcoop.ir/documents/co/',
                '/governance/elections',
            ],
            'continuous elections' => [
                '/governance/elections',
                'انتخابات دائمی بدون نامزد',
                'https://docs.earthcoop.ir/documents/ex/',
                '/governance',
            ],
            'justice' => [
                '/justice',
                'عدالت، حق، آزادی',
                'https://docs.earthcoop.ir/documents/ch/',
                '/commons',
            ],
            'commons' => [
                '/commons',
                'زمین، منابع مشترک و حق همگانی',
                'https://docs.earthcoop.ir/documents/co/',
                '/justice',
            ],
            'modern cooperation' => [
                '/cooperative',
                'تعاون نوین',
                'https://docs.earthcoop.ir/documents/co/',
                '/cooperative/global',
            ],
            'global cooperation' => [
                '/cooperative/global',
                'تعاون جهانی',
                'https://docs.earthcoop.ir/documents/co/',
                '/cooperative',
            ],
            'local to global governance' => [
                '/governance/local-to-global',
                'حکمرانی از محله تا جهان',
                'https://docs.earthcoop.ir/documents/ex/',
                '/governance',
            ],
            'ownership' => [
                '/economy/ownership',
                'مالکیت در EarthCoop',
                'https://docs.earthcoop.ir/documents/econ/',
                '/commons',
            ],
            'accountable technology' => [
                '/technology',
                'حکمرانی دیجیتال',
                'https://docs.earthcoop.ir/documents/co/',
                '/governance',
            ],
        ];
    }

    public function test_elections_page_does_not_equate_earthcoop_with_liquid_democracy(): void
    {
        $html = $this->get('/governance/elections')->assertOk()->getContent();

        $this->assertStringContainsString('دموکراسی سیال', $html);
        $this->assertStringContainsString('یکسان نیست', $html);
    }

    public function test_glass_economy_preserves_privacy_as_part_of_transparency_contract(): void
    {
        $html = $this->get('/economy/glass')->assertOk()->getContent();

        $this->assertStringContainsString('شفاف و امن', $html);
        $this->assertStringContainsString('حریم خصوصی', $html);
    }

    public function test_commons_page_preserves_legitimate_private_ownership(): void
    {
        $html = $this->get('/commons')->assertOk()->getContent();

        $this->assertStringContainsString('مالکیت خصوصی مشروع', $html);
        $this->assertStringContainsString('دسترنج', $html);
    }

    public function test_cooperative_page_treats_platform_cooperative_as_comparison_not_synonym(): void
    {
        $html = $this->get('/cooperative')->assertOk()->getContent();

        $this->assertStringContainsString('تعاونی پلتفرمی', $html);
        $this->assertStringContainsString('معادل', $html);
    }

    public function test_local_to_global_governance_does_not_claim_federalism_equivalence(): void
    {
        $html = $this->get('/governance/local-to-global')->assertOk()->getContent();

        $this->assertStringContainsString('فدرالیسم', $html);
        $this->assertStringContainsString('یکسان', $html);
    }

    public function test_ownership_page_preserves_both_common_rights_and_private_property(): void
    {
        $html = $this->get('/economy/ownership')->assertOk()->getContent();

        $this->assertStringContainsString('حق مالکانه همگانی', $html);
        $this->assertStringContainsString('مالکیت خصوصی مشروع', $html);
    }

    public function test_technology_page_keeps_najm_hoda_capabilities_publication_safe(): void
    {
        $html = $this->get('/technology')->assertOk()->getContent();

        $this->assertStringContainsString('نجم هدا', $html);
        $this->assertStringContainsString('قابلیت‌های در حال توسعه', $html);
    }
}
