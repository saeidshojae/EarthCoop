<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\Users\UserManagementService;

class FounderUserSuspensionDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected UserManagementService $users
    ) {}

    /** @return array<string,mixed> */
    public function requestSuspend(User $user, int $requestedBy, ?string $reasonCode = null): array
    {
        if ($user->isSystemIdentity()) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'system_identity_protected'];
        }

        if ((string) $user->status === 'suspended') {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'user_already_suspended'];
        }

        return $this->requests->prepare('users', 'suspend_user', [
            'entity_type' => 'user',
            'entity_id' => (int) $user->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'users-suspend-' . (int) $user->id,
            'source_event' => 'founder_ops_user_lifecycle',
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success' => false, 'status' => 'forbidden', 'reason' => 'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId);
        if (! is_array($pending)) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'approval_request_not_pending'];
        }

        if ((string) data_get($pending, 'plan_item.domain') !== 'users'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'suspend_user'
            || (string) data_get($pending, 'context.entity_type') !== 'user') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $userId = (int) data_get($pending, 'context.entity_id', 0);
        $user = $userId > 0 ? User::query()->find($userId) : null;
        if (! $user) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'user_not_found'];
        }
        if ($user->isSystemIdentity()) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'system_identity_protected'];
        }
        if ((string) $user->status === 'suspended') {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'user_already_suspended'];
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }

        if ($decision === 'reject') {
            return ['success' => true, 'status' => 'rejected', 'user_id' => $userId];
        }

        return $this->execution->execute(
            'users',
            'suspend_user',
            fn (): array => $this->users->suspend($user),
            $requestId,
            [
                'entity_type' => 'user',
                'entity_id' => $userId,
                'requested_by' => $founderId,
                'reason_code' => (string) data_get($pending, 'context.reason_code', ''),
            ]
        );
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            (array) config('najm-hoda-founder-action-policy.founder_approval.user_ids', [])
        )));
    }
}
