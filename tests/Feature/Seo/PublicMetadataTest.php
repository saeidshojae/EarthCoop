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

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertCount(2, $matches[1]);
        $types = array_map(fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR)['@type'], $matches[1]);
        $this->assertSame(['Organization', 'WebSite'], $types);
    }

    public function test_published_page_prefers_translated_meta_values(): void
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
            ->assertSee('href="https://earthcoop.ir/pages/translated-meta"', false);
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
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('content="noindex,nofollow"', false);
        }
    }

    public function test_authenticated_application_pages_are_not_indexable(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/home')
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
