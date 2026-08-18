<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;

/**
 * Keeps S7 guided operations composable while the generic Secretariat Draft
 * assistant remains the single controller-facing integration point.
 *
 * The historical class name is retained during S7 to avoid destabilizing the
 * already-validated Chat API wiring. Read-only readiness runs first, then the
 * most specific evidence/governance operations before correspondence helpers.
 */
class NajmHodaSecretariatCorrespondenceRouter
{
    public function __construct(
        private readonly NajmHodaSecretariatDraftReadinessAssistant $readiness,
        private readonly NajmHodaSecretariatExecutionReportAssistant $executionReports,
        private readonly NajmHodaSecretariatGovernanceDraftAssistant $governanceDrafts,
        private readonly NajmHodaSecretariatInternalCorrespondenceAssistant $internal,
        private readonly NajmHodaSecretariatIncomingCorrespondenceAssistant $incoming,
        private readonly NajmHodaSecretariatCorrespondenceAssistant $outgoing,
    ) {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        $response = $this->readiness->intercept($actor, $pageContext, $message);
        if (is_array($response)) {
            return $response;
        }

        $response = $this->executionReports->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) {
            return $response;
        }

        $response = $this->governanceDrafts->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) {
            return $response;
        }

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
