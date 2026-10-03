<?php

namespace App\Services\Push;

use Firebase\JWT\JWT;
use Throwable;

class HmsAccessTokenProvider
{
    private const AUDIENCE = 'https://oauth-login.cloud.huawei.com/oauth2/v3/token';

    public function __construct(
        private readonly ?string $credentialsPath = null,
    ) {
    }

    public function token(): ?string
    {
        $path = $this->credentialsPath;
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        try {
            $credentials = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($credentials)) {
                return null;
            }

            $keyId = $credentials['key_id'] ?? null;
            $issuer = $credentials['sub_account'] ?? null;
            $privateKey = $credentials['private_key'] ?? null;
            if (! is_string($keyId) || $keyId === '' || ! is_string($issuer) || $issuer === '' || ! is_string($privateKey) || $privateKey === '') {
                return null;
            }

            $now = time();

            return JWT::encode([
                'aud' => self::AUDIENCE,
                'iss' => $issuer,
                'sub' => $issuer,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $privateKey, 'PS256', $keyId);
        } catch (Throwable) {
            return null;
        }
    }
}
