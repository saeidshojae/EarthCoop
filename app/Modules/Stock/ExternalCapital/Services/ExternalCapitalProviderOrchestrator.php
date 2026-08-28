<?php

namespace App\Modules\Stock\ExternalCapital\Services;

use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\ExternalPaymentReconciliation;
use App\Modules\Stock\Services\ExternalCapitalPaymentService;
use RuntimeException;

final class ExternalCapitalProviderOrchestrator
{
    public function __construct(
        private readonly AuthoritativeRateProvider $rates,
        private readonly ExternalPaymentProvider $payments,
        private readonly ExternalCapitalPaymentService $domain,
    ) {}

    public function createPaymentIntentForAuction(
        Auction $auction,
        int $golAmount,
        string $currency,
        string $intentKey,
        string $referenceType,
        int|string $referenceId,
        array $metadata = [],
        ?\DateTimeInterface $expiresAt = null,
    ): ExternalPaymentIntent {
        $quote = $this->rates->quote($golAmount, $currency);
        if ($quote->source !== $this->rates->sourceIdentifier()) {
            throw new RuntimeException('Authoritative rate provider source identifier does not match quote snapshot source.');
        }

        $provider = trim($this->payments->providerIdentifier());
        if ($provider === '' || $provider === 'unavailable') {
            throw new RuntimeException('External payment provider is unavailable.');
        }

        $intent = $this->domain->createIntentForAuction(
            $auction,
            $quote,
            $intentKey,
            $referenceType,
            $referenceId,
            $provider,
            $metadata,
            $expiresAt,
        );

        $providerIntent = $this->payments->createIntent($intent);
        if ($providerIntent->currency !== $intent->currency || $providerIntent->amountMinor !== (int) $intent->amount_minor) {
            throw new RuntimeException('External payment provider intent amount/currency does not match EarthCoop payment intent.');
        }

        return $this->domain->markPending(
            $intent->intent_key,
            $providerIntent->providerIntentId,
            $provider,
        );
    }

    public function reconcileVerifiedWebhook(
        string $intentKey,
        string $payload,
        array $headers = [],
    ): ExternalPaymentReconciliation {
        $provider = trim($this->payments->providerIdentifier());
        if ($provider === '' || $provider === 'unavailable') {
            throw new RuntimeException('External payment provider is unavailable.');
        }

        $event = $this->payments->verifyWebhook($payload, $headers);

        return $this->domain->reconcile(
            $intentKey,
            $event->eventId,
            $event->eventType,
            $event->resultStatus,
            $event->amountMinor,
            strtoupper($event->currency),
            $event->eventId,
            $event->providerPaymentId,
            $provider,
            $event->payload,
            $event->metadata,
            $event->occurredAt,
        );
    }
}
