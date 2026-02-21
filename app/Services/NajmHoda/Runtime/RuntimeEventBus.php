<?php

namespace App\Services\NajmHoda\Runtime;

interface RuntimeEventBus
{
    public function emit(string $event, array $payload = []): void;

    public function recent(?string $event = null, int $limit = 50): array;

    public function clear(): void;
}

