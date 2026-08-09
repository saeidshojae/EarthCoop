<?php

namespace App\Modules\Stock\Settlement;

interface SettlementGateway
{
    public function channel(): string;

    public function reserve(SettlementRequest $request): SettlementReceipt;

    public function release(SettlementRequest $request): SettlementReceipt;

    public function settle(SettlementRequest $request): SettlementReceipt;

    public function refund(SettlementRequest $request): SettlementReceipt;
}
