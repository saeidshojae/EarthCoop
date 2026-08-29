<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Pricing\BaharGolConverter;
use App\Modules\Stock\Services\StockValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockBaharValuationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bahar_is_converted_to_gol_exactly_without_float_arithmetic(): void
    {
        $converter = app(BaharGolConverter::class);

        $this->assertSame(1_250_000_000, $converter->toGol('12500000'));
        $this->assertSame(1_234, $converter->toGol('12.34'));
        $this->assertSame(100, $converter->toGol('1.00'));
        $this->assertSame('12.34', $converter->toBaharString(1_234));
        $this->assertSame('1', $converter->toBaharString(100));
    }

    public function test_bahar_precision_beyond_one_gol_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bahar supports at most two decimal places');

        app(BaharGolConverter::class)->toGol('12.345');
    }

    public function test_non_positive_bahar_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(BaharGolConverter::class)->toGol('0');
    }

    public function test_stock_valuation_is_persisted_in_canonical_gol_and_per_share_price_is_derived(): void
    {
        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 1,
            'startup_valuation_gol' => 1,
            'total_shares' => 100,
            'base_share_price' => 1,
            'base_share_price_gol' => 1,
            'available_shares' => 10,
        ]);

        $configured = app(StockValuationService::class)->configure(
            $stock,
            '12000000',
            100_000_000,
            10_000_000,
            'EarthCoop canonical valuation'
        );

        $this->assertSame(1_200_000_000, (int) $configured->startup_valuation_gol);
        $this->assertSame(100_000_000, (int) $configured->total_shares);
        $this->assertSame(10_000_000, (int) $configured->available_shares);
        $this->assertSame(12, (int) $configured->base_share_price_gol);
        $this->assertSame('EarthCoop canonical valuation', $configured->info);
    }

    public function test_non_integral_gol_per_share_is_rejected_instead_of_rounded(): void
    {
        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 1,
            'startup_valuation_gol' => 1,
            'total_shares' => 1,
            'base_share_price' => 1,
            'base_share_price_gol' => 1,
            'available_shares' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exact integer Gol value per share');

        app(StockValuationService::class)->configure($stock, '1', 3, 1, null);
    }

    public function test_legacy_decimal_fields_do_not_drive_canonical_values(): void
    {
        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 999999999,
            'startup_valuation_gol' => 100,
            'total_shares' => 100,
            'base_share_price' => 999999,
            'base_share_price_gol' => 1,
            'available_shares' => 10,
        ]);

        $configured = app(StockValuationService::class)->configure($stock, '1000', 100, 10, null);

        $this->assertSame(100_000, (int) $configured->startup_valuation_gol);
        $this->assertSame(1_000, (int) $configured->base_share_price_gol);
        $this->assertSame(100_000, (int) $configured->canonical_market_cap_gol);
    }
}
