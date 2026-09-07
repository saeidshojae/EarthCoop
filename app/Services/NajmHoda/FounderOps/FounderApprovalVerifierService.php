<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderApprovalVerifierService
{
    public function __construct(protected NajmHodaAutonomyApprovalService $approvals) {}

    /** @return array<string,mixed> */
    public function verify(string $requestId, string $domain, string $action): array
    {
        $allowedFounderIds = array_values(array_map('intval', (array) config(
            'najm-hoda-founder-action-policy.founder_approval.user_ids',
            []
        )));

        if ($allowedFounderIds === []) {
            return ['valid' => false, 'reason' => 'founder_approver_not_configured'];
        }

        $request = collect($this->approvals->history(1000, 'approved'))
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId);

        if (! is_array($request)) {
            return ['valid' => false, 'reason' => 'approved_request_not_found'];
        }

        $expectedAction = 'founder_ops:' . $domain . '.' . $action;
        if ((string) ($request['action'] ?? '') !== $expectedAction) {
            return ['valid' => false, 'reason' => 'approval_action_mismatch'];
        }

        $decisionBy = (int) ($request['decision_by'] ?? 0);
        if ($decisionBy <= 0 || ! in_array($decisionBy, $allowedFounderIds, true)) {
            return ['valid' => false, 'reason' => 'decision_not_by_authorized_founder'];
        }

        return [
            'valid' => true,
            'reason' => 'verified_founder_approval',
            'request_id' => $requestId,
            'decision_by' => $decisionBy,
            'decision_at' => $request['decision_at'] ?? null,
        ];
    }
}
