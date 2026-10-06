<?php

namespace Tests\Feature\Seo;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicMetadataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_home_emits_one_complete_metadata_contract_and_valid_json_ld(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<meta name="description"'));
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
        $this->assertStringContainsString('href="https://earthcoop.ir/"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('content="index,follow"', $html);
        $this->assertStringContainsString('rel="icon" type="image/png"', $html);
        $this->assertStringContainsString('/icons/earthcoop-brand-192.png', $html);
        $this->assertStringContainsString('property="og:image" content="https://earthcoop.ir/icons/earthcoop-brand-192.png"', $html);

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertCount(2, $matches[1]);
        $payloads = array_map(
            fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            $matches[1],
        );
        $this->assertSame(['Organization', 'WebSite'], array_column($payloads, '@type'));
        $this->assertSame('https://earthcoop.ir/icons/earthcoop-brand-192.png', $payloads[0]['logo']);
        $this->assertSame(['ارث‌کوپ', 'ارث کوپ'], $payloads[0]['alternateName']);
        $this->assertSame(['ارث‌کوپ', 'ارث کوپ'], $payloads[1]['alternateName']);
    }

    public function test_search_brand_icon_is_a_real_square_png_and_manifest_uses_it(): void
    {
        $iconPath = public_path('icons/earthcoop-brand-192.png');

        $this->assertFileExists($iconPath);
        $this->assertSame([192, 192], array_slice(getimagesize($iconPath), 0, 2));

        $manifest = json_decode(
            file_get_contents(public_path('manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('/icons/earthcoop-brand-192.png', $manifest['icons'][0]['src']);
        $this->assertSame('192x192', $manifest['icons'][0]['sizes']);
        $this->assertSame('image/png', $manifest['icons'][0]['type']);
        $this->assertStringContainsString('ارث‌کوپ', $manifest['description']);
    }

    public function test_home_is_a_concise_semantic_hub_for_core_earthcoop_topics(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('ارث‌کوپ', $html);
        $this->assertStringContainsString('اقتصاد آزاد مردمی', $html);
        $this->assertStringContainsString('حکمرانی مشارکتی', $html);
        $this->assertStringContainsString('href="/cooperative"', $html);
        $this->assertStringContainsString('href="/economy"', $html);
        $this->assertStringContainsString('href="/governance"', $html);
        $this->assertStringContainsString('href="/justice"', $html);
    }

    public function test_published_page_prefers_translated_meta_values_and_is_indexable(): void
    {
        $page = $this->page([
            'slug' => 'translated-meta',
            'title_translations' => ['fa' => 'عنوان فارسی'],
            'meta_title_translations' => ['fa' => 'عنوان متای فارسی'],
            'meta_description_translations' => ['fa' => 'توضیح متای فارسی'],
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('<title>عنوان متای فارسی</title>', false)
            ->assertSee('content="توضیح متای فارسی"', false)
            ->assertSee('href="https://earthcoop.ir/pages/translated-meta"', false)
            ->assertSee('content="index,follow"', false);
    }

    public function test_page_fallback_description_is_plain_text_and_length_limited(): void
    {
        $page = $this->page([
            'slug' => 'plain-description',
            'content_translations' => ['fa' => '<p>بخش اول &quot;نقل قول&quot;</p><p>بخش دوم با <strong>نشانه</strong> و '.str_repeat('ادامه ', 50).'</p>'],
        ]);

        $html = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $match);

        $this->assertNotEmpty($match[1] ?? null);
        $this->assertStringNotContainsString('<strong>', html_entity_decode($match[1]));
        $this->assertStringContainsString('بخش اول &quot;نقل قول&quot; بخش دوم', $match[1]);
        $this->assertStringNotContainsString('&amp;quot;', $match[1]);
        $this->assertLessThanOrEqual(163, mb_strlen(html_entity_decode($match[1])));
    }

    public function test_unpublished_page_remains_not_found(): void
    {
        $page = $this->page(['slug' => 'private-page', 'is_published' => false]);

        $this->get(route('pages.show', $page->slug))->assertNotFound();
    }

    public function test_terms_emits_one_canonical_and_description(): void
    {
        $html = $this->get('/terms')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<meta name="description"'));
        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
        $this->assertStringContainsString('href="https://earthcoop.ir/terms"', $html);
        $this->assertStringContainsString('content="index,follow"', $html);
    }

    public function test_auth_entry_points_are_not_indexable(): void
    {
        foreach (['/login', '/forgot-password'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('content="noindex,nofollow"', false);
        }

        $this->withSession(['registration_terms_accepted' => true])
            ->get('/register')
            ->assertOk()
            ->assertSee('content="noindex,nofollow"', false);
    }

    public function test_authenticated_application_pages_are_not_indexable(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/notifications')
            ->assertOk()
            ->assertSee('content="noindex,nofollow"', false);
    }

    private function page(array $attributes = []): Page
    {
        return Page::query()->create(array_merge([
            'title' => 'Default title',
            'slug' => 'page-'.uniqid(),
            'template' => 'default',
            'content' => '<p>Default content</p>',
            'is_published' => true,
            'show_in_header' => false,
        ], $attributes));
    }
}
