<?php

namespace App\Services\Push;

use App\Models\NativeDevice;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class HmsPushDeliveryGateway implements PushDeliveryGateway
{
    public function __construct(
        private HmsAccessTokenProvider $accessTokenProvider,
        private ?string $projectId,
    ) {
    }

    public function send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult
    {
        $accessToken = $this->accessTokenProvider->token();
        if (! is_string($accessToken) || $accessToken === '' || ! is_string($this->projectId) || $this->projectId === '') {
            return PushDeliveryResult::temporaryFailure('hms_credentials_missing');
        }

        try {
            $deviceToken = Crypt::decryptString((string) $device->push_token);
            $data = [
                'type' => $envelope->type,
            ];
            if ($envelope->link !== null) {
                $data['link'] = $envelope->link;
            }
            if ($envelope->context !== []) {
                $data['context'] = $envelope->context;
            }

            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->withHeaders(['push-type' => '0'])
                ->post(
                    sprintf('https://push-api.cloud.huawei.com/v3/%s/messages:send', rawurlencode($this->projectId)),
                    [
                        'payload' => [
                            'notification' => [
                                'title' => $envelope->title,
                                'body' => $envelope->body,
                                'clickAction' => ['actionType' => 0],
                            ],
                            'data' => json_encode($data, JSON_THROW_ON_ERROR),
                        ],
                        'target' => [
                            'token' => [$deviceToken],
                        ],
                    ],
                );

            $payload = $response->json();
            $code = is_array($payload) && isset($payload['code']) ? (string) $payload['code'] : null;

            if ($response->successful() && ($code === null || $code === '80000000')) {
                return PushDeliveryResult::success();
            }

            if (in_array($code, ['80300007', '80300028'], true)) {
                return PushDeliveryResult::invalidToken($code);
            }

            if ($response->status() === 429 || $response->serverError()) {
                return PushDeliveryResult::temporaryFailure($code ?? 'hms_temporary_failure');
            }

            return PushDeliveryResult::permanentFailure($code ?? 'hms_permanent_failure');
        } catch (Throwable) {
            return PushDeliveryResult::temporaryFailure('hms_provider_exception');
        }
    }
}
