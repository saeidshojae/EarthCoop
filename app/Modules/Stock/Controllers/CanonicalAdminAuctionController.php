<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\EarthCoopPrimaryOfferingPolicy;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Temporal\Context\TemporalContext;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\ValueObjects\LocalDate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class CanonicalAdminAuctionController extends Controller
{
    public function __construct(
        private readonly EarthCoopPrimaryOfferingPolicy $offeringPolicy,
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
    ) {
    }

    public function index(Request $request)
    {
        $query = Auction::with('bids');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('q')) {
            $search = (string) $request->input('q');
            $query->where(function ($nested) use ($search): void {
                $nested->where('info', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $context = $this->temporalContexts->defaultContext();
        if ($request->filled('date_from')) {
            try {
                $dateFrom = $this->parseFilterDate((string) $request->input('date_from'), $context);
                $query->where('start_time', '>=', $this->temporal->startOfDay($dateFrom, $context));
            } catch (\Throwable) {
                // Keep optional filters non-fatal, matching the mature admin behavior.
            }
        }
        if ($request->filled('date_to')) {
            try {
                $dateTo = $this->parseFilterDate((string) $request->input('date_to'), $context);
                $query->where('start_time', '<=', $this->temporal->endOfDay($dateTo, $context));
            } catch (\Throwable) {
                // Keep optional filters non-fatal, matching the mature admin behavior.
            }
        }

        if ($request->filled('price_min')) {
            $query->where('base_price', '>=', $request->input('price_min'));
        }
        if ($request->filled('price_max')) {
            $query->where('base_price', '<=', $request->input('price_max'));
        }
        if ($request->filled('volume_min')) {
            $query->where('shares_count', '>=', $request->input('volume_min'));
        }
        if ($request->filled('volume_max')) {
            $query->where('shares_count', '<=', $request->input('volume_max'));
        }

        $sortBy = (string) $request->input('sort_by', 'id');
        $sortOrder = (string) $request->input('sort_order', 'desc');
        if (in_array($sortBy, ['id', 'shares_count', 'base_price', 'start_time', 'ends_at', 'status', 'type', 'created_at'], true)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $auctions = $query->paginate(25)->appends($request->except('page'));
        $allAuctions = Auction::with('bids')->get();
        $stats = [
            'total_auctions' => $allAuctions->count(),
            'running_auctions' => $allAuctions->where('status', 'running')->count(),
            'scheduled_auctions' => $allAuctions->where('status', 'scheduled')->count(),
            'settled_auctions' => $allAuctions->whereIn('status', ['settled', 'completed'])->count(),
            'canceled_auctions' => $allAuctions->whereIn('status', ['canceled', 'cancelled'])->count(),
            'total_bids' => $allAuctions->sum(fn ($auction) => $auction->bids->count()),
            'total_volume' => $allAuctions->sum(fn ($auction) => $auction->bids->sum('quantity')),
            'total_capital' => $allAuctions->sum(fn ($auction) => $auction->bids->sum(fn ($bid) => ($bid->price ?? 0) * ($bid->quantity ?? 0))),
        ];
        $chartData = $this->getAuctionChartData($allAuctions, $context);
        $statusCounts = [
            'running' => $stats['running_auctions'],
            'scheduled' => $stats['scheduled_auctions'],
            'settled' => $stats['settled_auctions'],
            'canceled' => $stats['canceled_auctions'],
        ];
        $totalVolume = $auctions->sum(fn ($auction) => $auction->bids->sum('quantity'));

        $auctions->getCollection()->transform(function ($auction) {
            $bids = $auction->bids;
            $auction->bids_count = $bids->count();
            $auction->highest_bid = $bids->max('price') ?? null;
            $auction->lowest_bid = $bids->min('price') ?? null;
            $auction->total_bid_volume = $bids->sum('quantity');
            $auction->order_book = $bids->where('status', 'active')->sortByDesc('price')->take(5)->values();

            return $auction;
        });

        if ($request->filled('bids_min')) {
            $minimum = (int) $request->input('bids_min');
            $auctions->setCollection($auctions->getCollection()->reject(fn ($auction) => $auction->bids_count < $minimum));
        }
        if ($request->filled('bids_max')) {
            $maximum = (int) $request->input('bids_max');
            $auctions->setCollection($auctions->getCollection()->reject(fn ($auction) => $auction->bids_count > $maximum));
        }

        return view('Stock::admin_auction_list', compact('auctions', 'stats', 'statusCounts', 'totalVolume', 'chartData'));
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
        $this->normalizeLocalizedDateTimes($request);

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

    private function normalizeLocalizedDateTimes(Request $request): void
    {
        $context = $this->temporalContexts->defaultContext();
        $normalized = [];

        foreach (['start_time', 'end_time', 'ends_at'] as $field) {
            $value = $request->input($field);
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            try {
                $raw = trim((string) $value);
                $parseContext = $this->dateTimeParseContext($raw, $context);

                $normalized[$field] = $this->temporal
                    ->parseDateTime($raw, $parseContext)
                    ->format('Y-m-d H:i:s');
            } catch (InvalidArgumentException|\ValueError) {
                throw ValidationException::withMessages([
                    $field => __('validation.date', ['attribute' => $field]),
                ]);
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function parseFilterDate(string $value, TemporalContext $context): LocalDate
    {
        $raw = trim($value);
        $parseContext = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1
            ? $this->temporalContexts->forLocale('en', $context->timezone())
            : $context;

        return $this->temporal->parseDate($raw, $parseContext);
    }

    /** @return array{labels: array<int,string>, volumes: array<int,int|float>, prices: array<int,int|float>, counts: array<int,int>} */
    private function getAuctionChartData($auctions, TemporalContext $context): array
    {
        $labels = [];
        $volumes = [];
        $prices = [];
        $counts = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $formattedDate = $this->temporal->date($date, $context, 'short');
            $parts = preg_split('/[\/-]/u', $formattedDate) ?: [];
            $monthLabel = count($parts) >= 2 ? $parts[0].'/'.$parts[1] : $formattedDate;

            $monthAuctions = $auctions->filter(
                fn ($auction) => $auction->start_time && $auction->start_time->format('Y-m') === $date->format('Y-m'),
            );
            $monthVolume = $monthAuctions->sum(fn ($auction) => $auction->bids->sum('quantity'));
            $monthPrices = $monthAuctions->flatMap(fn ($auction) => $auction->bids->pluck('price')->filter());
            $monthAvgPrice = $monthPrices->count() > 0 ? $monthPrices->avg() : 0;

            $labels[] = $monthLabel;
            $volumes[] = $monthVolume;
            $prices[] = round($monthAvgPrice, 2);
            $counts[] = $monthAuctions->count();
        }

        return compact('labels', 'volumes', 'prices', 'counts');
    }

    private function dateTimeParseContext(string $value, TemporalContext $context): TemporalContext
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/', $value) === 1) {
            return $this->temporalContexts->forLocale('en', $context->timezone());
        }

        return $context;
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
