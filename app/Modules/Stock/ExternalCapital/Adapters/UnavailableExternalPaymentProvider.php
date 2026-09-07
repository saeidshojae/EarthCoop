<?php

namespace App\Modules\Stock\ExternalCapital\Adapters;

use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Dto\ProviderPaymentIntent;
use App\Modules\Stock\ExternalCapital\Dto\VerifiedPaymentEvent;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use RuntimeException;

final class UnavailableExternalPaymentProvider implements ExternalPaymentProvider
{
    public function providerIdentifier(): string
    {
        return 'unavailable';
    }

    public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
    {
        throw new RuntimeException('External payment provider is unavailable and external capital must remain fail-closed.');
    }

    public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
    {
        throw new RuntimeException('External payment provider is unavailable; webhook verification cannot proceed.');
    }
}
