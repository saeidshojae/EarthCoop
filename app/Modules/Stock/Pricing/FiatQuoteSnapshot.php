<?php

namespace App\Modules\Stock\Pricing;

use InvalidArgumentException;

final class FiatQuoteSnapshot
{
    public function __construct(
        public readonly int $golAmount,
        public readonly string $currency,
        public readonly int $fiatAmountMinor,
        public readonly int $rateNumerator,
        public readonly int $rateDenominator,
        public readonly string $source,
        public readonly string $quotedAt,
    ) {
        if ($golAmount <= 0 || $fiatAmountMinor <= 0 || $rateNumerator <= 0 || $rateDenominator <= 0) {
            throw new InvalidArgumentException('Quote amounts and rate ratio must be positive integers.');
        }
        $currency = strtoupper(trim($currency));
        if (! in_array($currency, ['IRR','USD'], true)) {
            throw new InvalidArgumentException('External quote currency must be IRR or USD.');
        }
        if (trim($source) === '' || trim($quotedAt) === '') {
            throw new InvalidArgumentException('Quote source and timestamp are required.');
        }
        if ($this->deterministicAmount($golAmount,$rateNumerator,$rateDenominator) !== $fiatAmountMinor) {
            throw new InvalidArgumentException('Fiat quote amount does not match deterministic integer rate calculation.');
        }
    }

    public static function fromRate(int $golAmount,string $currency,int $rateNumerator,int $rateDenominator,string $source,?\DateTimeInterface $quotedAt=null): self
    {
        $fiat=self::deterministicAmountStatic($golAmount,$rateNumerator,$rateDenominator);
        return new self($golAmount,strtoupper($currency),$fiat,$rateNumerator,$rateDenominator,$source,($quotedAt??now())->format(DATE_ATOM));
    }

    public function toArray(): array
    {
        return [
            'gol_amount'=>$this->golAmount,
            'currency'=>$this->currency,
            'fiat_amount_minor'=>$this->fiatAmountMinor,
            'rate_numerator'=>$this->rateNumerator,
            'rate_denominator'=>$this->rateDenominator,
            'rounding'=>'half_up_integer',
            'source'=>$this->source,
            'quoted_at'=>$this->quotedAt,
        ];
    }

    private function deterministicAmount(int $gol,int $num,int $den): int
    {
        return self::deterministicAmountStatic($gol,$num,$den);
    }

    private static function deterministicAmountStatic(int $gol,int $num,int $den): int
    {
        if ($gol<=0 || $num<=0 || $den<=0) throw new InvalidArgumentException('Quote inputs must be positive integers.');
        if ($gol > intdiv(PHP_INT_MAX,$num)) throw new InvalidArgumentException('Quote multiplication exceeds integer range.');
        $product=$gol*$num;
        $half=intdiv($den,2);
        if ($product > PHP_INT_MAX-$half) throw new InvalidArgumentException('Quote rounding exceeds integer range.');
        return intdiv($product+$half,$den);
    }
}
