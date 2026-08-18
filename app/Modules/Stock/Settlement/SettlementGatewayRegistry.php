<?php

namespace App\Modules\Stock\Settlement;

use RuntimeException;

final class SettlementGatewayRegistry
{
    /** @var array<string, SettlementGateway> */
    private array $gateways = [];

    /**
     * @param iterable<SettlementGateway> $gateways
     */
    public function __construct(iterable $gateways = [])
    {
        foreach ($gateways as $gateway) {
            $this->register($gateway);
        }
    }

    public function register(SettlementGateway $gateway): void
    {
        $channel = $gateway->channel();

        if (! in_array($channel, SettlementChannel::all(), true)) {
            throw new RuntimeException("Settlement gateway exposes unknown channel: {$channel}");
        }

        if (isset($this->gateways[$channel])) {
            throw new RuntimeException("Settlement gateway already registered for channel: {$channel}");
        }

        $this->gateways[$channel] = $gateway;
    }

    public function forChannel(string $channel): SettlementGateway
    {
        if (! in_array($channel, SettlementChannel::all(), true)) {
            throw new RuntimeException("Unknown settlement channel: {$channel}");
        }

        if (! isset($this->gateways[$channel])) {
            throw new RuntimeException("No settlement gateway registered for channel: {$channel}");
        }

        return $this->gateways[$channel];
    }
}
