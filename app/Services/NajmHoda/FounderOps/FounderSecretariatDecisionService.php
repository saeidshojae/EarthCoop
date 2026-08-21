<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderSecretariatDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected SecretariatRecordService $records,
        protected SecretariatCaseService $cases
    ) {}

    /** @return array<string,mixed> */
    public function requestRegister(SecretariatRecord $record, int $requestedBy, ?string $reasonCode = null): array
    {
        if ($record->registry_number !== null) {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'record_already_registered'];
        }
        if ((string) $record->status !== 'pending_approval') {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'record_not_pending_approval'];
        }

        return $this->requests->prepare('secretariat', 'register_formal_record', [
            'entity_type' => 'secretariat_record',
            'entity_id' => (int) $record->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'secretariat-register-' . (int) $record->id,
            'source_event' => 'founder_ops_secretariat_record',
        ]);
    }

    /** @return array<string,mixed> */
    public function requestCloseCase(SecretariatCase $case, int $requestedBy, ?string $reasonCode = null): array
    {
        if ((string) $case->status === 'closed') {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'case_already_closed'];
        }
        if ((string) $case->status === 'archived') {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'archived_case_is_terminal'];
        }

        return $this->requests->prepare('secretariat', 'close_case', [
            'entity_type' => 'secretariat_case',
            'entity_id' => (int) $case->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'secretariat-close-case-' . (int) $case->id,
            'source_event' => 'founder_ops_secretariat_case',
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

        $domain = (string) data_get($pending, 'plan_item.domain');
        $action = (string) data_get($pending, 'plan_item.domain_action');
        $entityType = (string) data_get($pending, 'context.entity_type');
        $entityId = (int) data_get($pending, 'context.entity_id', 0);

        if ($domain !== 'secretariat' || ! in_array($action, ['register_formal_record', 'close_case'], true)) {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $expectedType = $action === 'register_formal_record' ? 'secretariat_record' : 'secretariat_case';
        if ($entityType !== $expectedType || $entityId < 1) {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_entity_mismatch'];
        }

        $founder = User::query()->find($founderId);
        if (! $founder) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'founder_user_not_found'];
        }

        $record = null;
        $case = null;
        if ($action === 'register_formal_record') {
            $record = SecretariatRecord::query()->find($entityId);
            if (! $record) {
                return ['success' => false, 'status' => 'not_found', 'reason' => 'secretariat_record_not_found'];
            }
            if ($record->registry_number !== null) {
                return ['success' => false, 'status' => 'skipped', 'reason' => 'record_already_registered'];
            }
            if ((string) $record->status !== 'pending_approval') {
                return ['success' => false, 'status' => 'blocked', 'reason' => 'record_not_pending_approval'];
            }
        } else {
            $case = SecretariatCase::query()->find($entityId);
            if (! $case) {
                return ['success' => false, 'status' => 'not_found', 'reason' => 'secretariat_case_not_found'];
            }
            if ((string) $case->status === 'closed') {
                return ['success' => false, 'status' => 'skipped', 'reason' => 'case_already_closed'];
            }
            if ((string) $case->status === 'archived') {
                return ['success' => false, 'status' => 'blocked', 'reason' => 'archived_case_is_terminal'];
            }
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }
        if ($decision === 'reject') {
            return ['success' => true, 'status' => 'rejected', 'entity_type' => $entityType, 'entity_id' => $entityId];
        }

        if ($action === 'register_formal_record') {
            return $this->execution->execute(
                'secretariat',
                'register_formal_record',
                function () use ($record, $founder): array {
                    $registered = $this->records->register($record, $founder);
                    return [
                        'record_id' => (int) $registered->id,
                        'record_status' => (string) $registered->status,
                        'registry_number' => (string) $registered->registry_number,
                    ];
                },
                $requestId,
                ['entity_type' => 'secretariat_record', 'entity_id' => $entityId, 'requested_by' => $founderId]
            );
        }

        return $this->execution->execute(
            'secretariat',
            'close_case',
            function () use ($case, $founder): array {
                $closed = $this->cases->transition($case, 'closed', $founder);
                return [
                    'case_id' => (int) $closed->id,
                    'case_status' => (string) $closed->status,
                    'closed_by' => (int) $closed->closed_by,
                ];
            },
            $requestId,
            ['entity_type' => 'secretariat_case', 'entity_id' => $entityId, 'requested_by' => $founderId]
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
