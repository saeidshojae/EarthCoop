<?php

namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Models\Auction;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CanonicalAuctionController extends AuctionController
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
    ) {
    }

    public function export(Auction $auction): StreamedResponse
    {
        $auction->load('bids');
        $fileName = 'auction-' . $auction->id . '-export.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];
        $context = $this->temporalContexts->defaultContext();

        $callback = function () use ($auction, $context): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Auction ID', 'Stock ID', 'Shares Count', 'Base Price', 'Status', 'Start Time', 'Ends At']);
            fputcsv($handle, [
                $auction->id,
                $auction->stock_id,
                $auction->shares_count,
                $auction->base_price,
                $auction->status,
                $auction->start_time ? $this->temporal->dateTime($auction->start_time, $context, 'short') : '',
                $auction->ends_at ? $this->temporal->dateTime($auction->ends_at, $context, 'short') : '',
            ]);
            fputcsv($handle, []);
            fputcsv($handle, ['BID_ID', 'USER_ID', 'PRICE', 'QUANTITY', 'STATUS', 'CREATED_AT']);

            $bidsForExport = $auction->bids->sort(function ($a, $b) {
                $priceA = $a->price ?? 0;
                $priceB = $b->price ?? 0;
                if ($priceA == $priceB) {
                    return strtotime($a->created_at) <=> strtotime($b->created_at);
                }

                return $priceB <=> $priceA;
            })->values();

            foreach ($bidsForExport as $bid) {
                fputcsv($handle, [
                    $bid->id,
                    $bid->user_id,
                    $bid->price,
                    $bid->quantity,
                    $bid->status,
                    $bid->created_at ? $this->temporal->dateTime($bid->created_at, $context, 'short') : '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
