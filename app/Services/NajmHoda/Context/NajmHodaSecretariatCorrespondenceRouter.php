<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;

/**
 * Keeps S7 guided operations composable while the generic Secretariat Draft
 * assistant remains the single controller-facing integration point.
 */
class NajmHodaSecretariatCorrespondenceRouter
{
    public function __construct(
        private readonly NajmHodaSecretariatReferralAssistant $referrals,
        private readonly NajmHodaSecretariatWorkQueueAssistant $workQueue,
        private readonly NajmHodaSecretariatRegistrationAdvisor $registrationAdvisor,
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
        $response = $this->referrals->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) return $response;

        $response = $this->workQueue->intercept($actor, $pageContext, $message);
        if (is_array($response)) return $response;

        $response = $this->registrationAdvisor->intercept($actor, $pageContext, $message);
        if (is_array($response)) return $response;

        $response = $this->readiness->intercept($actor, $pageContext, $message);
        if (is_array($response)) return $response;

        $response = $this->executionReports->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) return $response;

        $response = $this->governanceDrafts->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) return $response;

        $response = $this->internal->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) return $response;

        $response = $this->incoming->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($response)) return $response;

        return $this->outgoing->intercept($actor, $pageContext, $message, $conversationId);
    }
}
