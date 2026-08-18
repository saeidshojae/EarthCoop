<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;

/**
 * Keeps S7 correspondence capabilities composable while the generic Secretariat
 * Draft assistant remains the single controller-facing integration point.
 */
class NajmHodaSecretariatCorrespondenceRouter
{
    public function __construct(
        private readonly NajmHodaSecretariatInternalCorrespondenceAssistant $internal,
        private readonly NajmHodaSecretariatIncomingCorrespondenceAssistant $incoming,
        private readonly NajmHodaSecretariatCorrespondenceAssistant $outgoing,
    ) {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        $response = $this->internal->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) {
            return $response;
        }

        $response = $this->incoming->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) {
            return $response;
        }

        return $this->outgoing->intercept($actor, $pageContext, $message, $conversationId);
    }
}
