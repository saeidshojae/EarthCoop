<?php

namespace Tests\Feature;

use App\Http\Middleware\GroupChatContentSecurityPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GroupChatViteCspContractTest extends TestCase
{
    public function test_local_group_chat_csp_allows_vite_dev_server_assets_and_hmr(): void
    {
        $this->app['env'] = 'local';

        $middleware = new GroupChatContentSecurityPolicy();
        $request = Request::create('/groups/chat/99', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('http://localhost:5173', $csp);
        $this->assertStringContainsString('http://127.0.0.1:5173', $csp);
        $this->assertStringContainsString('ws://localhost:5173', $csp);
        $this->assertStringContainsString('ws://127.0.0.1:5173', $csp);
    }

    public function test_non_local_group_chat_csp_does_not_allow_vite_dev_server_origins(): void
    {
        $this->app['env'] = 'production';

        $middleware = new GroupChatContentSecurityPolicy();
        $request = Request::create('/groups/chat/99', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('localhost:5173', $csp);
        $this->assertStringNotContainsString('127.0.0.1:5173', $csp);
    }
}
