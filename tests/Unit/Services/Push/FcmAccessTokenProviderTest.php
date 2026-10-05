<?php

namespace Tests\Unit\Services\Push;

use App\Services\Push\FcmAccessTokenProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class FcmAccessTokenProviderTest extends TestCase
{
    public function test_actual_google_auth_exchange_is_bounded_and_does_not_follow_redirects(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $file = tempnam(sys_get_temp_dir(), 'fcm-oauth-test-');
        file_put_contents($file, json_encode(['type' => 'service_account', 'project_id' => 'project-123', 'client_email' => 'sender@project-123.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']));
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"synthetic-oauth-token","expires_in":3600,"token_type":"Bearer"}')]));
        $stack->push(Middleware::history($history));
        try {
            $provider = new FcmAccessTokenProvider($file, new Client(['handler' => $stack]));
            $this->assertSame('synthetic-oauth-token', $provider->token());
            $this->assertCount(1, $history);
            $this->assertSame('https://oauth2.googleapis.com/token', (string) $history[0]['request']->getUri());
            $this->assertSame(5, $history[0]['options']['connect_timeout']);
            $this->assertSame(10, $history[0]['options']['timeout']);
            $this->assertFalse($history[0]['options']['allow_redirects']);
        } finally {
            unlink($file);
        }
    }
}
