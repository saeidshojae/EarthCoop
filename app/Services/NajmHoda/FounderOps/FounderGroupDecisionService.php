<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderGroupRoleChangeIntent;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\TemporaryGroupRoleService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FounderGroupDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected TemporaryGroupRoleService $roles
    ) {}

    /** @return array<string,mixed> */
    public function requestRoleChange(
        GroupUser $membership,
        int $targetRole,
        ?CarbonInterface $expiresAt,
        int $requestedBy,
        ?string $reasonCode = null
    ): array {
        if ($targetRole < 0 || $targetRole > 4) {
            throw new InvalidArgumentException('Group role must be between 0 and 4.');
        }
        if ($expiresAt && $expiresAt->isPast()) {
            throw new InvalidArgumentException('Group role expiry must be in the future.');
        }

        $reasonCode = $reasonCode ?: 'group-role-change-' . (int) $membership->id . '-' . $targetRole;
        $key = hash('sha256', implode('|', [
            (string) $membership->id,
            (string) $targetRole,
            $expiresAt?->toIso8601String() ?? 'unlimited',
            (string) $requestedBy,
            $reasonCode,
        ]));

        $intent = FounderGroupRoleChangeIntent::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'group_user_id' => (int) $membership->id,
                'target_role' => $targetRole,
                'expires_at' => $expiresAt,
                'requested_by' => $requestedBy,
                'reason_code' => $reasonCode,
                'status' => FounderGroupRoleChangeIntent::PENDING,
            ]
        );

        if ((string) $intent->status === FounderGroupRoleChangeIntent::EXECUTED) {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'role_change_already_executed', 'intent_id' => (int) $intent->id];
        }
        if ((string) $intent->status === FounderGroupRoleChangeIntent::REJECTED) {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'role_change_intent_rejected', 'intent_id' => (int) $intent->id];
        }

        return $this->requests->prepare('groups', 'change_member_role', [
            'entity_type' => 'founder_group_role_change_intent',
            'entity_id' => (int) $intent->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode,
            'source_event' => 'founder_ops_group_role_change',
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

        if ((string) data_get($pending, 'plan_item.domain') !== 'groups'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'change_member_role'
            || (string) data_get($pending, 'context.entity_type') !== 'founder_group_role_change_intent') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $intentId = (int) data_get($pending, 'context.entity_id', 0);
        $intent = FounderGroupRoleChangeIntent::query()->find($intentId);
        if (! $intent) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'group_role_change_intent_not_found'];
        }
        if ((string) $intent->status !== FounderGroupRoleChangeIntent::PENDING) {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'group_role_change_intent_not_pending'];
        }

        $membership = GroupUser::query()->find((int) $intent->group_user_id);
        if (! $membership) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'group_membership_not_found'];
        }
        if ($intent->expires_at && $intent->expires_at->isPast()) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'group_role_change_intent_expired'];
        }

        $founder = User::query()->find($founderId);
        if (! $founder) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'founder_user_not_found'];
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }

        if ($decision === 'reject') {
            $intent->forceFill(['status' => FounderGroupRoleChangeIntent::REJECTED])->save();
            return ['success' => true, 'status' => 'rejected', 'intent_id' => (int) $intent->id];
        }

        return $this->execution->execute(
            'groups',
            'change_member_role',
            function () use ($intent, $membership, $founder): array {
                return DB::transaction(function () use ($intent, $membership, $founder): array {
                    $updated = $this->roles->apply(
                        $membership,
                        (int) $intent->target_role,
                        $intent->expires_at,
                        $founder,
                        'najm_hoda_founder_ops'
                    );

                    $intent->forceFill([
                        'status' => FounderGroupRoleChangeIntent::EXECUTED,
                        'executed_by' => (int) $founder->id,
                        'executed_at' => now(),
                    ])->save();

                    return [
                        'intent_id' => (int) $intent->id,
                        'group_user_id' => (int) $updated->id,
                        'group_id' => (int) $updated->group_id,
                        'user_id' => (int) $updated->user_id,
                        'role' => (int) $updated->role,
                        'role_override_active' => (bool) $updated->role_override_active,
                        'role_override_expires_at' => $updated->role_override_expires_at?->toIso8601String(),
                    ];
                });
            },
            $requestId,
            ['entity_type' => 'founder_group_role_change_intent', 'entity_id' => $intentId, 'requested_by' => $founderId]
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
