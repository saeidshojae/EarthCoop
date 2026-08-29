<?php

namespace App\Http\Middleware;

use App\Modules\Stock\Models\Stock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CanonicalStockAdminValuation
{
    private const GOL_PER_BAHAR = 100;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin/stock') || ! $request->isMethod('post') || ! $request->has('startup_valuation_bahar')) {
            return $next($request);
        }

        $valuationGol = $this->parseBaharToGol((string) $request->input('startup_valuation_bahar'));
        $totalShares = filter_var($request->input('total_shares'), FILTER_VALIDATE_INT);

        if ($valuationGol === null || $totalShares === false || $totalShares < 1 || $valuationGol % $totalShares !== 0) {
            throw ValidationException::withMessages([
                'startup_valuation_bahar' => 'ارزش‌گذاری باید به مقدار دقیقی از گل تبدیل شود و بر تعداد کل سهام بخش‌پذیر باشد.',
            ]);
        }

        $baseSharePriceGol = intdiv($valuationGol, $totalShares);

        // Legacy decimal columns remain transitional compatibility fields only.
        // Canonical accounting is always integer Gol.
        $request->merge([
            'startup_valuation' => $valuationGol / self::GOL_PER_BAHAR,
            'base_share_price' => $baseSharePriceGol / self::GOL_PER_BAHAR,
        ]);

        $response = $next($request);

        $stock = Stock::query()->first();
        if ($stock) {
            $stock->forceFill([
                'issuer_type' => $stock->issuer_type ?: 'earthcoop',
                'startup_valuation_gol' => $valuationGol,
                'base_share_price_gol' => $baseSharePriceGol,
            ])->saveQuietly();
        }

        return $response;
    }

    private function parseBaharToGol(string $value): ?int
    {
        $normalized = trim(str_replace([',', ' '], '', $value));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        if (strlen($whole) > 16) {
            return null;
        }

        $gol = ((int) $whole * self::GOL_PER_BAHAR) + (int) $fraction;

        return $gol > 0 ? $gol : null;
    }
}
