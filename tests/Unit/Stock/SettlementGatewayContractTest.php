<?php

namespace Tests\Unit\Stock;

use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementGateway;
use App\Modules\Stock\Settlement\SettlementGatewayRegistry;
use App\Modules\Stock\Settlement\SettlementReceipt;
use App\Modules\Stock\Settlement\SettlementRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SettlementGatewayContractTest extends TestCase
{
    public function test_request_requires_positive_integer_amount_and_idempotency_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SettlementRequest(
            SettlementChannel::ACTIVE_BAHAR,
            0,
            'bid:1:reserve',
            'bid',
            1,
        );
    }

    public function test_registry_resolves_registered_gateway_by_channel(): void
    {
        $gateway = new class implements SettlementGateway {
            public function channel(): string
            {
                return SettlementChannel::ACTIVE_BAHAR;
            }

            public function reserve(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::RESERVED, $request->amount, $request->idempotencyKey);
            }

            public function release(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::RELEASED, $request->amount, $request->idempotencyKey);
            }

            public function settle(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::SETTLED, $request->amount, $request->idempotencyKey);
            }

            public function refund(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::REFUNDED, $request->amount, $request->idempotencyKey);
            }
        };

        $registry = new SettlementGatewayRegistry([$gateway]);

        $this->assertSame($gateway, $registry->forChannel(SettlementChannel::ACTIVE_BAHAR));
    }

    public function test_registry_fails_closed_when_gateway_is_not_registered(): void
    {
        $this->expectException(RuntimeException::class);

        (new SettlementGatewayRegistry())->forChannel(SettlementChannel::EXTERNAL_IRR);
    }

    public function test_registry_rejects_duplicate_channel_registration(): void
    {
        $gateway = $this->fakeGateway(SettlementChannel::ACTIVE_BAHAR);
        $registry = new SettlementGatewayRegistry([$gateway]);

        $this->expectException(RuntimeException::class);
        $registry->register($this->fakeGateway(SettlementChannel::ACTIVE_BAHAR));
    }

    private function fakeGateway(string $channel): SettlementGateway
    {
        return new class($channel) implements SettlementGateway {
            public function __construct(private readonly string $settlementChannel)
            {
            }

            public function channel(): string
            {
                return $this->settlementChannel;
            }

            public function reserve(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::RESERVED, $request->amount, $request->idempotencyKey);
            }

            public function release(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::RELEASED, $request->amount, $request->idempotencyKey);
            }

            public function settle(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::SETTLED, $request->amount, $request->idempotencyKey);
            }

            public function refund(SettlementRequest $request): SettlementReceipt
            {
                return new SettlementReceipt($this->channel(), SettlementReceipt::REFUNDED, $request->amount, $request->idempotencyKey);
            }
        };
    }
}
