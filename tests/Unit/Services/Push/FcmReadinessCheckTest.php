<?php

namespace Tests\Unit\Services\Push;

use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\FcmReadinessCheck;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;
use Tests\TestCase;

class FcmReadinessCheckTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_validation_uses_fixed_topic_without_delivering_or_enabling_driver(): void
    {
        config()->set('services.push.driver', 'null');
        Http::fake(['*' => Http::response(['name' => 'projects/project-123/messages/validation'], 200)]);
        $result = $this->check()->check();
        $this->assertSame(['ready' => true, 'code' => 'fcm_validation_ready'], $result);
        $this->assertSame('null', config('services.push.driver'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/project-123/messages:send'
            && $request->hasHeader('Authorization', 'Bearer access-secret')
            && $request->data()['validate_only'] === true
            && data_get($request->data(), 'message.topic') === 'earthcoop-readiness-probe'
            && ! isset($request->data()['message']['token']));
        $this->assertStringNotContainsString('access-secret', json_encode($result));
        $this->assertStringNotContainsString('synthetic-private-secret', json_encode($result));
    }

    public function test_missing_or_malformed_credentials_stop_without_network(): void
    {
        Http::fake();
        foreach ([null, '/missing/credential.json', $this->file('{invalid')] as $path) {
            $result = (new FcmReadinessCheck($this->tokens(), 'project-123', $path))->check();
            $this->assertFalse($result['ready']);
        }
        Http::assertNothingSent();
    }

    public function test_project_identity_and_token_endpoint_mismatch_stop_before_oauth(): void
    {
        Http::fake();
        foreach ([['project_id' => 'other-project'], ['client_email' => 'sender@other-project.iam.gserviceaccount.com'], ['token_uri' => 'https://untrusted.example/token'], ['type' => 'authorized_user'], ['private_key' => '']] as $change) {
            $provider = new class extends FcmAccessTokenProvider
            {
                public function token(): ?string
                {
                    throw new RuntimeException('preflight must not call OAuth');
                }
            };
            $result = (new FcmReadinessCheck($provider, 'project-123', $this->file(json_encode(array_replace($this->credentials(), $change)))))->check();
            $this->assertSame(['ready' => false, 'code' => 'fcm_credentials_invalid'], $result);
        }
        Http::assertNothingSent();
    }

    public function test_oauth_failure_is_sanitized_and_stops_validation_request(): void
    {
        Http::fake();
        $this->assertSame(['ready' => false, 'code' => 'fcm_oauth_failed'], $this->check(null)->check());
        Http::assertNothingSent();
    }

    public function test_provider_rejections_and_malformed_success_never_report_ready(): void
    {
        foreach ([401, 403, 429, 500, 503, 200] as $status) {
            Http::fake(['*' => Http::response(['error' => 'provider-private-detail'], $status)]);
            $result = $this->check()->check();
            $this->assertSame(['ready' => false, 'code' => 'fcm_validation_failed'], $result);
            $this->assertStringNotContainsString('provider-private-detail', json_encode($result));
        }
    }

    public function test_transport_exception_is_sanitized(): void
    {
        Http::fake(fn () => throw new RuntimeException('access-secret synthetic-private-secret'));
        $this->assertSame(['ready' => false, 'code' => 'fcm_validation_failed'], $this->check()->check());
    }

    public function test_public_or_nonprivate_and_oversized_credentials_are_rejected(): void
    {
        Http::fake();
        $path = $this->file(json_encode($this->credentials()));
        chmod($path, 0644);
        clearstatcache(true, $path);
        $this->assertFalse((new FcmReadinessCheck($this->tokens(), 'project-123', $path))->check()['ready']);
        $oversized = $this->file(str_repeat(' ', 65537));
        $this->assertFalse((new FcmReadinessCheck($this->tokens(), 'project-123', $oversized))->check()['ready']);
        $public = public_path('fcm-readiness-synthetic.json');
        file_put_contents($public, json_encode($this->credentials()));
        chmod($public, 0600);
        $this->files[] = $public;
        $this->assertFalse((new FcmReadinessCheck($this->tokens(), 'project-123', $public))->check()['ready']);
        Http::assertNothingSent();
    }

    public function test_oauth_exception_never_discloses_secret_text(): void
    {
        Http::fake();
        $provider = new class extends FcmAccessTokenProvider
        {
            public function token(): ?string
            {
                throw new RuntimeException('synthetic-private-secret');
            }
        };
        $result = (new FcmReadinessCheck($provider, 'project-123', $this->file(json_encode($this->credentials()))))->check();
        $this->assertSame(['ready' => false, 'code' => 'fcm_oauth_failed'], $result);
        Http::assertNothingSent();
    }

    public function test_relative_public_symlink_is_never_accepted_as_private(): void
    {
        Http::fake(['*' => Http::response(['name' => 'projects/project-123/messages/validation'], 200)]);
        $target = $this->file(json_encode($this->credentials()));
        $link = public_path('fcm-readiness-synthetic-link.json');
        symlink($target, $link);
        $this->files[] = $link;
        $cwd = getcwd();
        try {
            chdir(base_path());
            $result = (new FcmReadinessCheck($this->tokens(), 'project-123', 'public/fcm-readiness-synthetic-link.json'))->check();
            $this->assertSame(['ready' => false, 'code' => 'fcm_credentials_invalid'], $result);
            Http::assertNothingSent();
        } finally {
            chdir($cwd);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_open_basedir_warning_is_sanitized_as_credential_failure(): void
    {
        Http::fake();
        ini_set('open_basedir', base_path().PATH_SEPARATOR.sys_get_temp_dir());
        $result = (new FcmReadinessCheck($this->tokens(), 'project-123', '/fcm-private-blocked/secret.json'))->check();
        $this->assertSame(['ready' => false, 'code' => 'fcm_credentials_invalid'], $result);
        Http::assertNothingSent();
    }

    private function check(?string $token = 'access-secret'): FcmReadinessCheck
    {
        return new FcmReadinessCheck($this->tokens($token), 'project-123', $this->file(json_encode($this->credentials())));
    }

    private function tokens(?string $token = 'access-secret'): FcmAccessTokenProvider
    {
        return new class($token) extends FcmAccessTokenProvider
        {
            public function __construct(private readonly ?string $value) {}

            public function token(): ?string
            {
                return $this->value;
            }
        };
    }

    private function credentials(): array
    {
        return ['type' => 'service_account', 'project_id' => 'project-123', 'client_email' => 'sender@project-123.iam.gserviceaccount.com', 'private_key' => 'synthetic-private-secret', 'token_uri' => 'https://oauth2.googleapis.com/token'];
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fcm-readiness-');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
