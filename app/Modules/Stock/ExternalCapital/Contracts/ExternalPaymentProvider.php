<?php

namespace App\Modules\Stock\ExternalCapital\Contracts;

use App\Modules\Stock\ExternalCapital\Dto\ProviderPaymentIntent;
use App\Modules\Stock\ExternalCapital\Dto\VerifiedPaymentEvent;
use App\Modules\Stock\Models\ExternalPaymentIntent;

interface ExternalPaymentProvider
{
    public function providerIdentifier(): string;

    public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent;

    public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent;
}
