<?php

namespace App\Modules\Stock\ExternalCapital\Adapters;

use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Dto\ProviderPaymentIntent;
use App\Modules\Stock\ExternalCapital\Dto\VerifiedPaymentEvent;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ZarinpalExternalPaymentProvider implements ExternalPaymentProvider
{
    public function providerIdentifier(): string
    {
        return 'zarinpal';
    }

    public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
    {
        $this->assertIrrIntent($intent);
        $config = $this->configuration();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($config['timeout_seconds'])
                ->post($config['base_url'] . '/payment/request.json', [
                    'merchant_id' => $config['merchant_id'],
                    'amount' => (int) $intent->amount_minor,
                    'description' => $config['description'],
                    'callback_url' => $this->intentCallbackUrl($config['callback_url'], (string) $intent->intent_key),
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('ZarinPal payment intent request failed.', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('ZarinPal payment intent request returned an unsuccessful response.');
        }

        $data = $response->json('data');
        if (! is_array($data) || (int) ($data['code'] ?? 0) !== 100) {
            throw new RuntimeException('ZarinPal did not approve the payment intent request.');
        }

        $authority = trim((string) ($data['authority'] ?? ''));
        if ($authority === '') {
            throw new RuntimeException('ZarinPal payment intent authority is missing.');
        }

        return new ProviderPaymentIntent(
            $authority,
            'IRR',
            (int) $intent->amount_minor,
            ['redirect_url' => $config['gateway_url'] . '/' . rawurlencode($authority)],
        );
    }

    public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
    {
        $this->assertIrrIntent($intent);
        $config = $this->configuration();
        $callback = $this->parseCallback($payload);

        $status = strtoupper(trim((string) ($callback['Status'] ?? $callback['status'] ?? '')));
        $authority = trim((string) ($callback['Authority'] ?? $callback['authority'] ?? ''));
        if ($authority === '') {
            throw new RuntimeException('ZarinPal callback authority is missing.');
        }

        $expectedAuthority = trim((string) $intent->provider_intent_id);
        if ($expectedAuthority !== '' && ! hash_equals($expectedAuthority, $authority)) {
            throw new RuntimeException('ZarinPal callback authority does not match the canonical EarthCoop payment intent.');
        }

        if ($status !== 'OK') {
            return new VerifiedPaymentEvent(
                'zarinpal:callback:' . hash('sha256', $authority . ':cancelled:' . $intent->intent_key),
                'payment_cancelled',
                'cancelled',
                (int) $intent->amount_minor,
                'IRR',
                null,
                ['authority' => $authority, 'status' => $status],
                ['verification' => 'callback_cancelled'],
            );
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($config['timeout_seconds'])
                ->post($config['base_url'] . '/payment/verify.json', [
                    'merchant_id' => $config['merchant_id'],
                    'amount' => (int) $intent->amount_minor,
                    'authority' => $authority,
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('ZarinPal payment verification request failed.', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('ZarinPal payment verification returned an unsuccessful response.');
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new RuntimeException('ZarinPal payment verification returned an invalid payload.');
        }

        $code = (int) ($data['code'] ?? 0);
        if (! in_array($code, [100, 101], true)) {
            throw new RuntimeException('ZarinPal payment verification did not confirm the payment.');
        }

        $refId = trim((string) ($data['ref_id'] ?? ''));
        if ($refId === '') {
            throw new RuntimeException('ZarinPal confirmed payment reference id is missing.');
        }

        return new VerifiedPaymentEvent(
            'zarinpal:verify:' . hash('sha256', $authority . ':' . $refId . ':' . $intent->intent_key),
            'payment_confirmed',
            'confirmed',
            (int) $intent->amount_minor,
            'IRR',
            $refId,
            ['authority' => $authority, 'verification_code' => $code],
            ['verification' => $code === 101 ? 'already_verified' : 'verified'],
        );
    }

    private function assertIrrIntent(ExternalPaymentIntent $intent): void
    {
        if (strtoupper(trim((string) $intent->currency)) !== 'IRR') {
            throw new InvalidArgumentException('ZarinPal external payment provider supports IRR only; USD remains fail-closed.');
        }
        if ((int) $intent->amount_minor <= 0) {
            throw new InvalidArgumentException('ZarinPal payment amount must be positive.');
        }
    }

    private function configuration(): array
    {
        $merchantId = trim((string) config('stock.external_capital.providers.zarinpal.merchant_id', ''));
        $baseUrl = rtrim(trim((string) config('stock.external_capital.providers.zarinpal.base_url', '')), '/');
        $gatewayUrl = rtrim(trim((string) config('stock.external_capital.providers.zarinpal.gateway_url', '')), '/');
        $callbackUrl = trim((string) config('stock.external_capital.providers.zarinpal.callback_url', ''));
        $description = trim((string) config('stock.external_capital.providers.zarinpal.description', ''));
        $timeout = max(1, (int) config('stock.external_capital.providers.zarinpal.timeout_seconds', 8));

        if ($merchantId === '' || $baseUrl === '' || $gatewayUrl === '' || $callbackUrl === '' || $description === '') {
            throw new RuntimeException('ZarinPal external payment provider is not fully configured.');
        }

        return [
            'merchant_id' => $merchantId,
            'base_url' => $baseUrl,
            'gateway_url' => $gatewayUrl,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'timeout_seconds' => $timeout,
        ];
    }

    private function intentCallbackUrl(string $callbackUrl, string $intentKey): string
    {
        $intentKey = trim($intentKey);
        if ($intentKey === '') {
            throw new RuntimeException('ZarinPal callback requires the canonical EarthCoop payment intent key.');
        }

        $fragment = '';
        $fragmentPosition = strpos($callbackUrl, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($callbackUrl, $fragmentPosition);
            $callbackUrl = substr($callbackUrl, 0, $fragmentPosition);
        }

        $separator = str_contains($callbackUrl, '?')
            ? (str_ends_with($callbackUrl, '?') || str_ends_with($callbackUrl, '&') ? '' : '&')
            : '?';

        return $callbackUrl
            . $separator
            . http_build_query(['intent' => $intentKey], '', '&', PHP_QUERY_RFC3986)
            . $fragment;
    }

    private function parseCallback(string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new RuntimeException('ZarinPal callback payload is empty.');
        }

        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($payload, $parsed);
        if (! is_array($parsed) || $parsed === []) {
            throw new RuntimeException('ZarinPal callback payload is invalid.');
        }

        return $parsed;
    }
}
