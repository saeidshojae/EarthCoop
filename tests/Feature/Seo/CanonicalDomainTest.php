<?php

namespace Tests\Feature\Seo;

use App\Support\Seo\CanonicalUrl;
use Illuminate\Http\Request;
use Tests\TestCase;

class CanonicalDomainTest extends TestCase
{
    public function test_net_root_redirects_permanently_to_ir(): void
    {
        $this->get('http://earthcoop.net/')
            ->assertStatus(301)
            ->assertRedirect('https://earthcoop.ir/');
    }

    public function test_net_redirect_preserves_encoded_path_and_query(): void
    {
        $target = '/pages/%D8%AF%D8%B1%D8%A8%D8%A7%D8%B1%D9%87?ref=one&lang=fa';

        $this->get('https://earthcoop.net'.$target)
            ->assertStatus(301)
            ->assertRedirect('https://earthcoop.ir'.$target);
    }

    public function test_www_net_redirects_but_ir_and_test_hosts_do_not_loop(): void
    {
        $this->get('https://www.earthcoop.net/terms')
            ->assertStatus(301)
            ->assertRedirect('https://earthcoop.ir/terms');

        $this->get('https://earthcoop.ir/terms')->assertOk();
        $this->get('http://localhost/terms')->assertOk();
    }

    public function test_canonical_url_never_uses_an_untrusted_request_host(): void
    {
        $request = Request::create('https://evil.example/pages/about?source=attack');

        $this->assertSame(
            'https://earthcoop.ir/pages/about',
            app(CanonicalUrl::class)->forRequest($request),
        );
    }

    public function test_invalid_runtime_origin_fails_closed_to_earthcoop_ir(): void
    {
        config(['seo.canonical_origin' => 'https://attacker.example']);

        $this->get('https://earthcoop.net/terms')
            ->assertStatus(301)
            ->assertRedirect('https://earthcoop.ir/terms');

        $this->get('https://evil.example/terms')
            ->assertOk()
            ->assertSee('href="https://earthcoop.ir/terms"', false)
            ->assertDontSee('attacker.example');
    }
}
