<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class NajmHodaRuntimeAuthorityFactory
{
    public function __construct(
        private readonly NajmHodaCapabilityRegistry $capabilities,
        private readonly NajmHodaResourceAuthorizationService $resourceAuthorization,
    ) {
    }

    public function propose(
        User $actor,
        string $action,
        array $input,
        string $source = 'internal',
    ): NajmHodaRuntimeActionAuthority {
        $this->assertAuthorized($actor, $action, $input);

        return NajmHodaRuntimeActionAuthority::propose($actor->id, $source);
    }

    public function apply(
        User $actor,
        string $action,
        array $input,
        string $source,
        string $consentEvidenceId,
    ): NajmHodaRuntimeActionAuthority {
        if (trim($consentEvidenceId) === '') {
            throw new InvalidArgumentException('consent_evidence_required');
        }

        $this->assertAuthorized($actor, $action, $input);

        return NajmHodaRuntimeActionAuthority::apply($actor->id, $source);
    }

    private function assertAuthorized(User $actor, string $action, array $input): void
    {
        $validation = $this->capabilities->validateInput($action, $input);
        if (! (bool) ($validation['valid'] ?? false)) {
            throw new InvalidArgumentException((string) ($validation['reason'] ?? 'capability_validation_failed'));
        }

        $resource = $this->resourceAuthorization->authorize($actor->id, $action, $input);
        if (! (bool) ($resource['allowed'] ?? false)) {
            throw new AuthorizationException((string) ($resource['reason'] ?? 'resource_not_accessible'));
        }
    }
}
