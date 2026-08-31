<?php

namespace App\Modules\Stock\ExternalCapital\Adapters;

use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ServixGold24AuthoritativeRateProvider implements AuthoritativeRateProvider
{
    private const ASSET = 'GOLD_24_RLS';
    private const SOURCE = 'servix:gold24:irr:v1';
    private const GOL_PER_GRAM_PURE_GOLD = 1000;

    public function sourceIdentifier(): string
    {
        return self::SOURCE;
    }

    public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
    {
        $currency = strtoupper(trim($currency));
        if ($currency !== 'IRR') {
            throw new InvalidArgumentException('Servix 24K gold rate provider supports IRR only; other currencies remain fail-closed.');
        }
        if ($golAmount <= 0) {
            throw new InvalidArgumentException('Gol amount must be positive.');
        }

        $apiKey = trim((string) config('stock.external_capital.providers.servix.api_key', ''));
        $baseUrl = rtrim(trim((string) config('stock.external_capital.providers.servix.base_url', '')), '/');
        $timeout = max(1, (int) config('stock.external_capital.providers.servix.timeout_seconds', 8));
        if ($apiKey === '' || $baseUrl === '') {
            throw new RuntimeException('Servix 24K rate provider is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->timeout($timeout)
                ->get($baseUrl . '/assets/' . self::ASSET);
        } catch (Throwable $e) {
            throw new RuntimeException('Servix 24K rate request failed.', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Servix 24K rate provider returned an unsuccessful response.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Servix 24K rate provider returned an invalid payload.');
        }
        if (($data['code'] ?? null) !== self::ASSET || strtoupper(trim((string) ($data['quoteUnit'] ?? ''))) !== 'RLS') {
            throw new RuntimeException('Servix response is not a direct 24K gold price quoted in IRR/RLS.');
        }

        $rawValue = $data['value'] ?? null;
        if (! is_string($rawValue) && ! is_int($rawValue)) {
            throw new RuntimeException('Servix 24K gold value must be an integer Rial amount.');
        }
        $value = trim((string) $rawValue);
        if ($value === '' || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            throw new RuntimeException('Servix 24K gold value must be a positive integer Rial amount.');
        }
        if (strlen($value) > strlen((string) PHP_INT_MAX) || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)) {
            throw new RuntimeException('Servix 24K gold value exceeds integer range.');
        }

        $businessTime = trim((string) ($data['businessTime'] ?? ''));
        if ($businessTime === '') {
            throw new RuntimeException('Servix 24K gold quote timestamp is required.');
        }
        try {
            $quotedAt = CarbonImmutable::parse($businessTime);
        } catch (Throwable $e) {
            throw new RuntimeException('Servix 24K gold quote timestamp is invalid.', 0, $e);
        }

        return FiatQuoteSnapshot::fromRate(
            $golAmount,
            'IRR',
            (int) $value,
            self::GOL_PER_GRAM_PURE_GOLD,
            self::SOURCE,
            $quotedAt,
        );
    }
}
