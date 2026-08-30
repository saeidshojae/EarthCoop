<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Dto\ExternalCapitalBidCheckout;
use App\Modules\Stock\ExternalCapital\Services\ExternalCapitalProviderOrchestrator;
use App\Modules\Stock\ExternalCapital\Services\ExternalCapitalReadinessGate;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Pricing\StockPricingService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ExternalCapitalBidCheckoutService
{
    public function __construct(
        private readonly StockPricingService $pricing,
        private readonly AuthoritativeRateProvider $rates,
        private readonly ExternalPaymentProvider $payments,
        private readonly ExternalCapitalReadinessGate $readiness,
        private readonly StockBidAcceptanceService $bids,
        private readonly ExternalCapitalPaymentService $domain,
        private readonly ExternalCapitalProviderOrchestrator $orchestrator,
    ) {}

    public function begin(
        int $userId,
        Auction $auction,
        int $priceGol,
        int $quantity,
        string $acceptanceKey,
        array $metadata = [],
    ): ExternalCapitalBidCheckout {
        return $this->beginCheckout(
            $userId,
            $auction,
            $priceGol,
            $quantity,
            $acceptanceKey,
            $metadata,
            false,
        );
    }

    public function beginUat(
        int $userId,
        Auction $auction,
        int $priceGol,
        int $quantity,
        string $acceptanceKey,
        array $metadata = [],
    ): ExternalCapitalBidCheckout {
        return $this->beginCheckout(
            $userId,
            $auction,
            $priceGol,
            $quantity,
            $acceptanceKey,
            array_merge($metadata, ['external_capital_uat' => true]),
            true,
        );
    }

    private function beginCheckout(
        int $userId,
        Auction $auction,
        int $priceGol,
        int $quantity,
        string $acceptanceKey,
        array $metadata,
        bool $uat,
    ): ExternalCapitalBidCheckout {
        if (! $auction->isActive()) {
            throw new RuntimeException('Auction is not active.');
        }

        $auction->loadMissing('stock');
        $auction->assertSettlementEligible();
        $this->pricing->assertCanonicalAuction($auction);

        $channel = (string) $auction->settlement_channel;
        if (! SettlementChannel::isExternal($channel)) {
            throw new RuntimeException('External capital checkout requires an external settlement channel.');
        }

        $currency = match ($channel) {
            SettlementChannel::EXTERNAL_IRR => 'IRR',
            SettlementChannel::EXTERNAL_USD => 'USD',
            default => throw new RuntimeException('Unsupported external settlement channel.'),
        };

        if ($uat) {
            $this->readiness->assertUatReadyForCurrency($currency);
        } else {
            $this->readiness->assertReadyForCurrency($currency);
        }

        $totalGol = $this->pricing->canonicalBidTotal($priceGol, $quantity);
        $quote = $this->rates->quote($totalGol, $currency);
        if ($quote->source !== $this->rates->sourceIdentifier()) {
            throw new RuntimeException('Authoritative rate provider source identifier does not match quote snapshot source.');
        }

        $provider = trim($this->payments->providerIdentifier());
        if ($provider === '' || $provider === 'unavailable') {
            throw new RuntimeException('External payment provider is unavailable.');
        }

        $bid = $this->bids->initiateExternalCapitalBid(
            $userId,
            $auction,
            $priceGol,
            $quantity,
            $acceptanceKey,
            $quote,
            $provider,
            $metadata,
        );

        $intent = $bid->fresh()->externalPaymentIntent;
        if (! $intent instanceof ExternalPaymentIntent) {
            throw new RuntimeException('External bid payment intent was not created.');
        }

        $providerIntent = $this->payments->createIntent($intent);
        if ($providerIntent->currency !== $intent->currency || $providerIntent->amountMinor !== (int) $intent->amount_minor) {
            throw new RuntimeException('External payment provider intent amount/currency does not match EarthCoop payment intent.');
        }

        $redirectUrl = trim((string) ($providerIntent->metadata['redirect_url'] ?? ''));
        if ($redirectUrl === '' || filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('External payment provider did not return a valid redirect URL.');
        }

        $pending = $this->domain->markPending(
            $intent->intent_key,
            $providerIntent->providerIntentId,
            $provider,
        );

        $freshBid = $bid->fresh();
        if ((int) $freshBid->external_payment_intent_id !== (int) $pending->id) {
            throw new RuntimeException('External payment intent is not linked to the canonical bid.');
        }

        return new ExternalCapitalBidCheckout($freshBid, $pending, $redirectUrl);
    }

    public function handleCallback(string $intentKey, string $payload, array $headers = []): Bid
    {
        return DB::transaction(function () use ($intentKey, $payload, $headers): Bid {
            $this->orchestrator->reconcileVerifiedWebhook($intentKey, $payload, $headers);

            $intent = ExternalPaymentIntent::query()
                ->where('intent_key', $intentKey)
                ->lockForUpdate()
                ->first();
            if (! $intent) {
                throw new RuntimeException('External payment intent not found after provider reconciliation.');
            }

            $bid = Bid::query()
                ->where('external_payment_intent_id', $intent->id)
                ->lockForUpdate()
                ->first();
            if (! $bid) {
                throw new RuntimeException('External payment intent is not linked to a bid.');
            }
            if ((string) $intent->reference_type !== 'stock_bid' || (string) $intent->reference_id !== (string) $bid->id) {
                throw new RuntimeException('External payment intent reference does not match its bid.');
            }

            if ($intent->status === ExternalPaymentIntent::CONFIRMED) {
                return $this->bids->activateExternallyFundedBid($bid);
            }

            if (in_array($intent->status, [
                ExternalPaymentIntent::PENDING,
                ExternalPaymentIntent::FAILED,
                ExternalPaymentIntent::CANCELLED,
            ], true)) {
                return $bid->fresh();
            }

            throw new RuntimeException('External bid callback produced an unsupported payment intent state.');
        });
    }
}
