<?php

namespace App\Services\Push;

use App\Models\NativeDevice;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class FcmHttpV1PushDeliveryGateway implements PushDeliveryGateway
{
    public function __construct(
        private FcmAccessTokenProvider $accessTokenProvider,
        private ?string $projectId,
    ) {
    }

    public function send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult
    {
        $accessToken = $this->accessTokenProvider->token();
        if (! is_string($accessToken) || $accessToken === '' || ! is_string($this->projectId) || $this->projectId === '') {
            return PushDeliveryResult::temporaryFailure('fcm_credentials_missing');
        }

        try {
            $deviceToken = Crypt::decryptString((string) $device->push_token);
            $data = ['type' => $envelope->type];
            if ($envelope->link !== null) {
                $data['link'] = json_encode($envelope->link, JSON_THROW_ON_ERROR);
            }
            if ($envelope->context !== []) {
                $data['context'] = json_encode($envelope->context, JSON_THROW_ON_ERROR);
            }

            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->post(
                    sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode($this->projectId)),
                    [
                        'message' => [
                            'token' => $deviceToken,
                            'notification' => [
                                'title' => $envelope->title,
                                'body' => $envelope->body,
                            ],
                            'data' => $data,
                        ],
                    ],
                );

            if ($response->successful()) {
                return PushDeliveryResult::success();
            }

            $payload = $response->json();
            $errorStatus = is_array($payload) ? data_get($payload, 'error.status') : null;
            $details = is_array($payload) ? data_get($payload, 'error.details', []) : [];
            $providerCode = $this->findProviderErrorCode($details) ?? (is_string($errorStatus) ? $errorStatus : null);

            if ($providerCode === 'UNREGISTERED') {
                return PushDeliveryResult::invalidToken('UNREGISTERED');
            }

            if ($response->status() === 429 || $response->serverError()) {
                return PushDeliveryResult::temporaryFailure($providerCode ?? 'fcm_temporary_failure');
            }

            return PushDeliveryResult::permanentFailure($providerCode ?? 'fcm_permanent_failure');
        } catch (Throwable) {
            return PushDeliveryResult::temporaryFailure('fcm_provider_exception');
        }
    }

    private function findProviderErrorCode(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        if (isset($value['errorCode']) && is_string($value['errorCode'])) {
            return $value['errorCode'];
        }

        foreach ($value as $child) {
            $code = $this->findProviderErrorCode($child);
            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }
}
