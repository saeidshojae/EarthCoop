<?php

namespace App\Http\Middleware;

use App\Support\Seo\CanonicalUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectToCanonicalDomain
{
    public function __construct(private readonly CanonicalUrl $canonicalUrl)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $redirectHosts = array_map(
            static fn (mixed $host): string => strtolower((string) $host),
            (array) config('seo.redirect_hosts', []),
        );

        if (in_array(strtolower($request->getHost()), $redirectHosts, true)) {
            return redirect()->away(
                $this->canonicalUrl->origin().$request->getRequestUri(),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        return $next($request);
    }
}
