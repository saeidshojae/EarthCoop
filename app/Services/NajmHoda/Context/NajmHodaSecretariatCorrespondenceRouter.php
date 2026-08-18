<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;

/**
 * Keeps S7 guided operations composable while the generic Secretariat Draft
 * assistant remains the single controller-facing integration point.
 *
 * The historical class name is retained during S7 to avoid destabilizing the
 * already-validated Chat API wiring; it now routes the evidence-grounded report
 * capability before the three correspondence capabilities.
 */
class NajmHodaSecretariatCorrespondenceRouter
{
    public function __construct(
        private readonly NajmHodaSecretariatExecutionReportAssistant $executionReports,
        private readonly NajmHodaSecretariatInternalCorrespondenceAssistant $internal,
        private readonly NajmHodaSecretariatIncomingCorrespondenceAssistant $incoming,
        private readonly NajmHodaSecretariatCorrespondenceAssistant $outgoing,
    ) {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        $response = $this->executionReports->intercept($actor, $pageContext, $message, $conversationId);
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
