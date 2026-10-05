<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class FcmReadinessCheck
{
    public function __construct(
        private FcmAccessTokenProvider $tokens,
        private ?string $projectId,
        private ?string $credentialsPath,
    ) {}

    public function check(): array
    {
        if (! $this->validCredentials()) {
            return $this->failure('fcm_credentials_invalid');
        }

        try {
            $token = $this->tokens->token();
        } catch (Throwable) {
            $token = null;
        }
        if (! is_string($token) || $token === '') {
            return $this->failure('fcm_oauth_failed');
        }

        try {
            $response = Http::acceptJson()->withToken($token)
                ->connectTimeout(5)->timeout(10)->withoutRedirecting()
                ->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($this->projectId).'/messages:send', [
                    'validate_only' => true,
                    'message' => [
                        'topic' => 'earthcoop-readiness-probe',
                        'data' => ['type' => 'earthcoop.readiness'],
                    ],
                ]);
            $name = $response->json('name');
            if ($response->successful() && is_string($name) && str_starts_with($name, 'projects/'.$this->projectId.'/messages/')) {
                return ['ready' => true, 'code' => 'fcm_validation_ready'];
            }
        } catch (Throwable) {
            // Never expose provider bodies, exception messages or bearer tokens.
        }
        return $this->failure('fcm_validation_failed');
    }

    private function validCredentials(): bool
    {
        $path = $this->credentialsPath;
        $project = $this->projectId;
        if (! is_string($project) || ! preg_match('/^[a-z][a-z0-9-]{4,28}[a-z0-9]$/', $project)
            || ! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            return false;
        }
        $resolved = realpath($path);
        $public = realpath(public_path());
        if ($resolved === false || ($public !== false && (str_starts_with($resolved, $public.DIRECTORY_SEPARATOR)
            || str_starts_with($path, $public.DIRECTORY_SEPARATOR))) || ((fileperms($resolved) & 0077) !== 0)) {
            return false;
        }
        try {
            $raw = @file_get_contents($resolved, false, null, 0, 65537);
            if (! is_string($raw) || strlen($raw) > 65536) {
                return false;
            }
            $key = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            return is_array($key)
                && ($key['type'] ?? null) === 'service_account'
                && ($key['project_id'] ?? null) === $project
                && is_string($key['client_email'] ?? null)
                && preg_match('/^[a-z0-9-]+@'.preg_quote($project, '/').'\.iam\.gserviceaccount\.com$/', $key['client_email'])
                && is_string($key['private_key'] ?? null) && $key['private_key'] !== ''
                && ($key['token_uri'] ?? null) === 'https://oauth2.googleapis.com/token';
        } catch (Throwable) {
            return false;
        }
    }

    private function failure(string $code): array
    {
        return ['ready' => false, 'code' => $code];
    }
}
