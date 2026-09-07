<?php

namespace App\Modules\Stock\Settlement;

use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use RuntimeException;

final class NajmBaharSettlementGateway implements SettlementGateway
{
    public function __construct(private readonly ActiveBaharReservationService $reservations) {}

    public function channel(): string { return SettlementChannel::ACTIVE_BAHAR; }

    public function reserve(SettlementRequest $request): SettlementReceipt
    {
        $this->assertChannel($request); $payer=$this->required($request->payerAccountNumber,'Payer account is required.');
        $r=$this->reservations->reserve($payer,$request->amount,$request->idempotencyKey,$request->referenceType,$request->referenceId,$request->metadata);
        return $this->receipt($request,SettlementReceipt::RESERVED,$r->reservation_key,['reservation_id'=>$r->id]);
    }

    public function release(SettlementRequest $request): SettlementReceipt
    {
        $this->assertChannel($request);
        $reservationKey=$this->reservationKey($request);
        $r=$this->reservations->release($reservationKey,$request->idempotencyKey);
        return $this->receipt($request,SettlementReceipt::RELEASED,$r->reservation_key,['reservation_id'=>$r->id]);
    }

    public function settle(SettlementRequest $request): SettlementReceipt
    {
        $this->assertChannel($request); $payee=$this->required($request->payeeAccountNumber,'Payee account is required.');
        $reservationKey=$this->reservationKey($request);
        $r=$this->reservations->settle($reservationKey,$payee,$request->idempotencyKey,$request->metadata);
        return $this->receipt($request,SettlementReceipt::SETTLED,$r->reservation_key,['reservation_id'=>$r->id]);
    }

    public function refund(SettlementRequest $request): SettlementReceipt
    {
        $this->assertChannel($request); $reservationKey=$this->reservationKey($request);
        $r=$this->reservations->refund($reservationKey,$request->amount,$request->idempotencyKey,$request->metadata);
        return $this->receipt($request,SettlementReceipt::REFUNDED,$r->reservation_key,['reservation_id'=>$r->id,'refunded_total'=>$r->refunded_amount]);
    }

    private function reservationKey(SettlementRequest $request): string
    {
        $key=(string)($request->metadata['reservation_key']??'');
        if ($key==='') throw new RuntimeException('reservation_key metadata is required after reserve.');
        return $key;
    }

    private function assertChannel(SettlementRequest $request): void { if ($request->channel!==$this->channel()) throw new RuntimeException('Settlement request channel does not match Najm Bahar gateway.'); }
    private function required(?string $value,string $message): string { if ($value===null || trim($value)==='') throw new RuntimeException($message); return $value; }
    private function receipt(SettlementRequest $request,string $status,string $providerReference,array $metadata=[]): SettlementReceipt { return new SettlementReceipt($this->channel(),$status,$request->amount,$request->idempotencyKey,$providerReference,array_merge($request->metadata,$metadata)); }
}
