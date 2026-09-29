<?php

namespace App\Support\Seo;

use Carbon\CarbonInterface;

final readonly class SitemapEntry
{
    public function __construct(
        public string $location,
        public ?CarbonInterface $lastModified = null,
    ) {
    }
}
