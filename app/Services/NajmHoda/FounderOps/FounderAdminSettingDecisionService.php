<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\Admin\AdminSettingManagementService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderAdminSettingDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected AdminSettingManagementService $settings
    ) {}

    /** @return array<string,mixed> */
    public function requestChange(string $key, mixed $value, int $requestedBy, ?string $reasonCode = null): array
    {
        $proposal = $this->settings->recommend($key, $value);

        return $this->requests->prepare('admin_settings', 'change_setting', [
            'entity_type' => 'system_setting',
            'entity_id' => 1,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'admin-setting-' . $key,
            'source_event' => 'founder_ops_admin_setting',
            'setting_key' => $key,
            'setting_value' => $proposal['proposed_value'],
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId);
        if (! is_array($pending)) {
            return ['success'=>false,'status'=>'not_found','reason'=>'approval_request_not_pending'];
        }

        if ((string) data_get($pending, 'plan_item.domain') !== 'admin_settings'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'change_setting'
            || (string) data_get($pending, 'context.entity_type') !== 'system_setting') {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }

        $key = (string) data_get($pending, 'context.setting_key', '');
        $value = data_get($pending, 'context.setting_value');
        $this->settings->recommend($key, $value);

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) return $decisionResult;
        if ($decision === 'reject') {
            return ['success'=>true,'status'=>'rejected','setting_key'=>$key];
        }

        return $this->execution->execute(
            'admin_settings',
            'change_setting',
            fn (): array => $this->settings->change($key, $value),
            $requestId,
            [
                'entity_type'=>'system_setting',
                'entity_id'=>1,
                'requested_by'=>$founderId,
                'setting_key'=>$key,
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
