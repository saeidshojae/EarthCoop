<?php

namespace Tests\Unit\Services\Push;

use App\Services\Push\HmsAccessTokenProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HmsAccessTokenProviderTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function credentials(array $values): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hms-test-');
        $this->files[] = $path;
        file_put_contents($path, json_encode($values, JSON_THROW_ON_ERROR));
        return $path;
    }

    public function test_obtains_and_caches_android_oauth_token(): void
    {
        Http::fake(['https://oauth-login.cloud.huawei.com/oauth2/v3/token' => Http::response([
            'access_token' => 'synthetic-oauth', 'token_type' => 'Bearer', 'expires_in' => 3600,
        ])]);
        $path = $this->credentials(['client_id' => 'client-456', 'client_secret' => 'synthetic-secret']);
        $provider = new HmsAccessTokenProvider($path, 'client-456');
        $this->assertSame('synthetic-oauth', $provider->token());
        $this->assertSame('synthetic-oauth', $provider->token());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth-login.cloud.huawei.com/oauth2/v3/token'
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'client-456'
            && $request['client_secret'] === 'synthetic-secret');
    }

    public function test_rejects_credentials_for_a_different_app_without_http(): void
    {
        Http::fake();
        $path = $this->credentials(['client_id' => 'different-client', 'client_secret' => 'synthetic-secret']);
        $this->assertNull((new HmsAccessTokenProvider($path, 'client-456'))->token());
        Http::assertNothingSent();
    }

    public function test_failed_oauth_is_not_cached_and_can_retry(): void
    {
        Http::fake(['*' => Http::sequence()->push(['error' => 'invalid_client'], 401)
            ->push(['access_token' => 'recovered', 'token_type' => 'Bearer', 'expires_in' => 3600], 200)]);
        $path = $this->credentials(['client_id' => 'client-456', 'client_secret' => 'synthetic-secret']);
        $provider = new HmsAccessTokenProvider($path, 'client-456');
        $this->assertNull($provider->token());
        $this->assertSame('recovered', $provider->token());
        Http::assertSentCount(2);
    }

    public function test_missing_and_legacy_credentials_fail_closed(): void
    {
        Http::fake();
        $this->assertNull((new HmsAccessTokenProvider(null, 'client-456'))->token());
        $path = $this->credentials(['key_id' => 'legacy', 'sub_account' => 'legacy', 'private_key' => 'synthetic-invalid-key']);
        $this->assertNull((new HmsAccessTokenProvider($path, 'client-456'))->token());
        Http::assertNothingSent();
    }
}
