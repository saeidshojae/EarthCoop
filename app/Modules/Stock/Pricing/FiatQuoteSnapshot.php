<?php

namespace App\Modules\Stock\Pricing;

use InvalidArgumentException;

final class FiatQuoteSnapshot
{
    public readonly int $golAmount;
    public readonly string $currency;
    public readonly int $fiatAmountMinor;
    public readonly int $rateNumerator;
    public readonly int $rateDenominator;
    public readonly string $source;
    public readonly string $quotedAt;

    public function __construct(int $golAmount,string $currency,int $fiatAmountMinor,int $rateNumerator,int $rateDenominator,string $source,string $quotedAt)
    {
        $currency=strtoupper(trim($currency));
        if($golAmount<=0||$fiatAmountMinor<=0||$rateNumerator<=0||$rateDenominator<=0) throw new InvalidArgumentException('Quote amounts and rate ratio must be positive integers.');
        if(!in_array($currency,['IRR','USD'],true)) throw new InvalidArgumentException('External quote currency must be IRR or USD.');
        if(trim($source)===''||trim($quotedAt)==='') throw new InvalidArgumentException('Quote source and timestamp are required.');
        if(self::deterministicAmountStatic($golAmount,$rateNumerator,$rateDenominator)!==$fiatAmountMinor) throw new InvalidArgumentException('Fiat quote amount does not match deterministic integer rate calculation.');
        $this->golAmount=$golAmount; $this->currency=$currency; $this->fiatAmountMinor=$fiatAmountMinor;
        $this->rateNumerator=$rateNumerator; $this->rateDenominator=$rateDenominator; $this->source=$source; $this->quotedAt=$quotedAt;
    }

    public static function fromRate(int $golAmount,string $currency,int $rateNumerator,int $rateDenominator,string $source,?\DateTimeInterface $quotedAt=null): self
    {
        return new self($golAmount,$currency,self::deterministicAmountStatic($golAmount,$rateNumerator,$rateDenominator),$rateNumerator,$rateDenominator,$source,($quotedAt??now())->format(DATE_ATOM));
    }

    public static function fromArray(array $data): self
    {
        return new self((int)($data['gol_amount']??0),(string)($data['currency']??''),(int)($data['fiat_amount_minor']??0),(int)($data['rate_numerator']??0),(int)($data['rate_denominator']??0),(string)($data['source']??''),(string)($data['quoted_at']??''));
    }

    public function toArray(): array
    {
        return ['gol_amount'=>$this->golAmount,'currency'=>$this->currency,'fiat_amount_minor'=>$this->fiatAmountMinor,'rate_numerator'=>$this->rateNumerator,'rate_denominator'=>$this->rateDenominator,'rounding'=>'half_up_integer','source'=>$this->source,'quoted_at'=>$this->quotedAt];
    }

    private static function deterministicAmountStatic(int $gol,int $num,int $den): int
    {
        if($gol<=0||$num<=0||$den<=0) throw new InvalidArgumentException('Quote inputs must be positive integers.');
        if($gol>intdiv(PHP_INT_MAX,$num)) throw new InvalidArgumentException('Quote multiplication exceeds integer range.');
        $product=$gol*$num; $half=intdiv($den,2);
        if($product>PHP_INT_MAX-$half) throw new InvalidArgumentException('Quote rounding exceeds integer range.');
        return intdiv($product+$half,$den);
    }
}
