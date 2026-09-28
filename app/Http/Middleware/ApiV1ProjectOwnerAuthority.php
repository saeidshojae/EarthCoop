<?php

namespace App\Http\Middleware;

use App\Services\Actors\ActorBoundaryException;
use App\Services\Actors\ActorOperation;
use App\Services\Actors\ActorReference;
use App\Services\Actors\ActorRepresentationAuthorizationService;
use App\Services\Actors\ActorResolver;
use Closure;
use Illuminate\Http\Request;

class ApiV1ProjectOwnerAuthority
{
    public function __construct(
        private readonly ActorResolver $actors,
        private readonly ActorRepresentationAuthorizationService $representation,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $principal = $request->user();
        if (! $principal) {
            return $next($request);
        }

        $owner = $this->actors->referenceFor($principal);
        if ($request->exists('owner_actor')) {
            $value = $request->input('owner_actor');
            if (! is_string($value)) {
                throw ActorBoundaryException::invalidReference();
            }
            $owner = ActorReference::parse($value);
        }

        $this->representation->authorize($principal, $owner, ActorOperation::ProjectOwner);

        return $next($request);
    }
}
