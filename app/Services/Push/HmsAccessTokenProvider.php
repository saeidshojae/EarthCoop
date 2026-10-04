<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Http;
use Throwable;

class HmsAccessTokenProvider
{
    private ?string $cachedToken = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly ?string $credentialsPath = null,
        private readonly ?string $clientId = null,
    ) {
    }

    public function token(): ?string
    {
        if ($this->cachedToken !== null && time() < $this->expiresAt) {
            return $this->cachedToken;
        }
        $this->cachedToken = null;
        $path = $this->credentialsPath;
        if (! is_string($path) || $path === '' || ! is_file($path)
            || ! is_string($this->clientId) || trim($this->clientId) === '') {
            return null;
        }

        try {
            $credentials = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($credentials) || ($credentials['client_id'] ?? null) !== $this->clientId
                || ! is_string($credentials['client_secret'] ?? null) || trim($credentials['client_secret']) === '') {
                return null;
            }
            $response = Http::asForm()->acceptJson()->timeout(10)->post(
                'https://oauth-login.cloud.huawei.com/oauth2/v3/token',
                ['grant_type' => 'client_credentials', 'client_id' => $this->clientId,
                    'client_secret' => $credentials['client_secret']],
            );
            $data = $response->json();
            $token = is_array($data) ? ($data['access_token'] ?? null) : null;
            $expires = is_array($data) ? ($data['expires_in'] ?? null) : null;
            if (! $response->successful() || ! is_string($token) || trim($token) === ''
                || strcasecmp((string) ($data['token_type'] ?? ''), 'Bearer') !== 0
                || ! is_int($expires) || $expires <= 0) {
                return null;
            }
            $this->cachedToken = $token;
            $this->expiresAt = time() + max(0, $expires - 60);

            return $token;
        } catch (Throwable) {
            return null;
        }
    }
}
