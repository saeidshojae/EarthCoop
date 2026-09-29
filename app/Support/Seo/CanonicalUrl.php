<?php

namespace App\Support\Seo;

use Illuminate\Http\Request;

final class CanonicalUrl
{
    private const APPROVED_ORIGIN = 'https://earthcoop.ir';

    public function origin(): string
    {
        $configuredOrigin = rtrim((string) config('seo.canonical_origin', self::APPROVED_ORIGIN), '/');

        return hash_equals(self::APPROVED_ORIGIN, $configuredOrigin)
            ? $configuredOrigin
            : self::APPROVED_ORIGIN;
    }

    public function to(string $path = '/', array $query = []): string
    {
        $normalizedPath = '/'.ltrim($path, '/');
        $url = $this->origin().$normalizedPath;

        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    public function forRequest(Request $request): string
    {
        return $this->to($request->getPathInfo());
    }
}
