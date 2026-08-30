<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Services\ExternalCapitalBidCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

final class ExternalCapitalBidController extends Controller
{
    public function store(
        Request $request,
        $auction,
        ExternalCapitalBidCheckoutService $checkout,
    ): RedirectResponse {
        $validated = $request->validate([
            'price_gol' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) optional($request->user())->id;
        if ($userId <= 0) {
            abort(401);
        }

        $canonicalAuction = $auction instanceof Auction
            ? $auction
            : Auction::query()->findOrFail((int) $auction);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $result = $checkout->begin(
            $userId,
            $canonicalAuction,
            (int) $validated['price_gol'],
            (int) $validated['quantity'],
            'stock-bid:' . $idempotencyKey,
            [
                'channel' => 'web',
                'request_id' => $request->header('X-Request-ID'),
            ],
        );

        return redirect()->away($result->redirectUrl);
    }

    public function callback(
        Request $request,
        ExternalCapitalBidCheckoutService $checkout,
    ): RedirectResponse {
        $intentKey = trim((string) $request->query('intent', $request->query('intent_key', '')));
        if ($intentKey === '') {
            throw new RuntimeException('External payment callback intent key is required.');
        }

        $payload = http_build_query($request->query());
        $bid = $checkout->handleCallback($intentKey, $payload, $request->headers->all());

        return redirect(url('/auctions/' . $bid->auction_id));
    }
}
