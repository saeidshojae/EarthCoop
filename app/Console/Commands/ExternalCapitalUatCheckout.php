<?php

namespace App\Console\Commands;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Services\ExternalCapitalBidCheckoutService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ExternalCapitalUatCheckout extends Command
{
    protected $signature = 'stock:external-capital-uat
        {auction : Auction ID}
        {user : User ID}
        {--price-gol= : Bid price per share in Gol}
        {--quantity= : Number of shares}';

    protected $description = 'Run an isolated non-production external-capital provider UAT checkout';

    public function handle(ExternalCapitalBidCheckoutService $checkout): int
    {
        try {
            if (app()->environment('production')) {
                throw new RuntimeException('External capital UAT is forbidden in production.');
            }

            $auctionId = (int) $this->argument('auction');
            $userId = (int) $this->argument('user');
            $priceGol = (int) $this->option('price-gol');
            $quantity = (int) $this->option('quantity');

            if ($auctionId <= 0 || $userId <= 0 || $priceGol <= 0 || $quantity <= 0) {
                throw new RuntimeException('auction, user, --price-gol and --quantity must be positive integers.');
            }

            $auction = Auction::query()->findOrFail($auctionId);
            $acceptanceKey = hash('sha256', implode('|', [
                'external-capital-uat',
                $auctionId,
                $userId,
                $priceGol,
                $quantity,
                now()->format('Y-m-d-H-i-s-u'),
            ]));

            $result = $checkout->beginUat(
                $userId,
                $auction,
                $priceGol,
                $quantity,
                $acceptanceKey,
                ['uat_command' => true],
            );

            $intent = $result->paymentIntent;
            $quote = (array) $intent->quote_snapshot;

            $this->info('External capital UAT checkout created');
            $this->line('Auction: ' . $auctionId);
            $this->line('User: ' . $userId);
            $this->line('Intent: ' . $intent->intent_key);
            $this->line('Provider: ' . (string) $intent->provider);
            $this->line('Currency: ' . (string) $intent->currency);
            $this->line('Amount minor: ' . (int) $intent->amount_minor);
            $this->line('Quote source: ' . (string) ($quote['source'] ?? 'unknown'));
            $this->line('Redirect: ' . $result->redirectUrl);
            $this->warn('UAT only: production rollout flags and attestations were not modified.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('External capital UAT checkout blocked: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
