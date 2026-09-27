<?php

namespace App\Http\Support\Api\V1;

final class CursorOptions
{
    public function __construct(
        public readonly ?string $cursor,
        public readonly int $limit,
    ) {
    }
}
