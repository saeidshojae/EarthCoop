<?php

namespace App\Http\Support\Api\V1;

final class PageOptions
{
    public function __construct(
        public readonly int $number,
        public readonly int $size,
    ) {
    }
}
