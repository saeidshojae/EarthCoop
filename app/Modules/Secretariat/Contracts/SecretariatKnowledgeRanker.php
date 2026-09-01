<?php

namespace App\Modules\Secretariat\Contracts;

use Illuminate\Support\Collection;

interface SecretariatKnowledgeRanker
{
    /**
     * Rank already-authorized knowledge packets.
     *
     * Implementations must never load Secretariat records or broaden authority.
     * They receive only packets produced by the permission-safe retrieval layer.
     *
     * @param Collection<int,array<string,mixed>> $packets
     * @return Collection<int,array<string,mixed>>
     */
    public function rank(string $query, Collection $packets): Collection;
}
