<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\EarthCoopPrimaryOfferingPolicy;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CanonicalAdminAuctionController extends Controller
{
    public function __construct(private readonly EarthCoopPrimaryOfferingPolicy $offeringPolicy)
    {
    }

    public function create()
    {
        return view('Stock::admin_auction_create', ['stock' => Stock::query()->first()]);
    }

    public function edit(Auction $auction)
    {
        return view('Stock::admin_auction_create', [
            'stock' => $auction->stock ?: Stock::query()->first(),
            'auction' => $auction,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedCanonicalPayload($request, true);
        $stock = Stock::query()->findOrFail($data['stock_id']);

        $auction = new Auction($data);
        $auction->status = 'scheduled';
        $auction->market_type = 'primary';
        $auction->supply_source = 'treasury';
        $auction->quote_unit = 'gol';
        $auction->settlement_channel = $data['settlement_channel'];
        $auction->base_price = $this->legacyBaharPrice((int) $data['base_price_gol']);
        $auction->setRelation('stock', $stock);

        $this->assertOfferingPolicy($auction);
        $auction->save();

        return redirect()->route('admin.auction.index')->with('success', 'حراج عرضه اولیه با قیمت‌گذاری گل ثبت شد');
    }

    public function update(Request $request, Auction $auction)
    {
        $data = $this->validatedCanonicalPayload($request, false);
        $stock = Stock::query()->findOrFail($data['stock_id']);

        $auction->fill($data);
        $auction->market_type = 'primary';
        $auction->supply_source = 'treasury';
        $auction->quote_unit = 'gol';
        $auction->settlement_channel = $data['settlement_channel'];
        $auction->base_price = $this->legacyBaharPrice((int) $data['base_price_gol']);
        $auction->setRelation('stock', $stock);

        $this->assertOfferingPolicy($auction);
        $auction->save();

        return redirect()->route('admin.auction.index')->with('success', 'حراج عرضه اولیه بروزرسانی شد');
    }

    /** @return array<string,mixed> */
    private function validatedCanonicalPayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'stock_id' => ['required', 'integer', 'exists:stocks,id'],
            'shares_count' => ['required', 'integer', 'min:1'],
            'base_price_gol' => ['required', 'integer', 'min:1'],
            'settlement_channel' => ['required', 'in:' . SettlementChannel::ACTIVE_BAHAR . ',' . SettlementChannel::EXTERNAL_IRR],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'ends_at' => [$creating ? 'required' : 'nullable', 'date', 'after:start_time'],
            'type' => ['required', 'in:single_winner,uniform_price,pay_as_bid'],
            'settlement_mode' => ['required', 'in:auto,manual'],
            'lot_size' => ['required', 'integer', 'min:1'],
            'channel_id' => ['nullable', 'exists:groups,id'],
            'info' => ['nullable', 'string'],
        ]);
    }

    private function assertOfferingPolicy(Auction $auction): void
    {
        try {
            $this->offeringPolicy->assertEligible($auction);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'shares_count' => $exception->getMessage(),
            ]);
        }
    }

    private function legacyBaharPrice(int $golPerShare): string
    {
        return number_format($golPerShare / 100, 2, '.', '');
    }
}
