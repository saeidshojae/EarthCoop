<?php

namespace App\Services\Push;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Throwable;

class FcmAccessTokenProvider
{
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

            $provider = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $credentials,
            );
            $auth = $provider->fetchAuthToken();
            $token = $auth['access_token'] ?? null;

            return is_string($token) && $token !== '' ? $token : null;
        } catch (Throwable) {
            return null;
        }
    }
}
