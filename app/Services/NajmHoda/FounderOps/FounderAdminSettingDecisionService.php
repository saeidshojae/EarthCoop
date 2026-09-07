<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderAdminSettingIntent;
use App\Services\Admin\AdminSettingManagementService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use Illuminate\Support\Facades\DB;

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
        $reasonCode ??= 'admin-setting-' . $key;
        $normalized = $proposal['proposed_value'];
        $idempotencyKey = hash('sha256', implode('|', [
            $key,
            json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) $requestedBy,
            $reasonCode,
        ]));

        $intent = DB::transaction(function () use ($idempotencyKey, $key, $normalized, $requestedBy, $reasonCode): FounderAdminSettingIntent {
            $existing = FounderAdminSettingIntent::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) return $existing;

            return FounderAdminSettingIntent::query()->create([
                'idempotency_key' => $idempotencyKey,
                'setting_key' => $key,
                'setting_value' => ['value' => $normalized],
                'requested_by' => $requestedBy,
                'reason_code' => $reasonCode,
                'status' => FounderAdminSettingIntent::PENDING,
            ]);
        });

        if ($intent->status === FounderAdminSettingIntent::EXECUTED) {
            return ['success'=>true,'status'=>'already_executed','intent_id'=>(int)$intent->id,'setting_key'=>$key];
        }
        if ($intent->status === FounderAdminSettingIntent::REJECTED) {
            return ['success'=>false,'status'=>'rejected','reason'=>'setting_intent_already_rejected','intent_id'=>(int)$intent->id];
        }

        return $this->requests->prepare('admin_settings', 'change_setting', [
            'entity_type' => 'founder_admin_setting_intent',
            'entity_id' => (int) $intent->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode,
            'source_event' => 'founder_ops_admin_setting',
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
            || (string) data_get($pending, 'context.entity_type') !== 'founder_admin_setting_intent') {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }

        $intentId = (int) data_get($pending, 'context.entity_id', 0);
        $intent = FounderAdminSettingIntent::query()->find($intentId);
        if (! $intent) return ['success'=>false,'status'=>'not_found','reason'=>'setting_intent_not_found'];
        if ($intent->status === FounderAdminSettingIntent::EXECUTED) {
            return ['success'=>true,'status'=>'already_executed','intent_id'=>$intentId];
        }
        if ($intent->status !== FounderAdminSettingIntent::PENDING) {
            return ['success'=>false,'status'=>'invalid_state','reason'=>'setting_intent_not_pending'];
        }

        $key = (string) $intent->setting_key;
        $value = data_get($intent->setting_value, 'value');
        $this->settings->recommend($key, $value);

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) return $decisionResult;

        if ($decision === 'reject') {
            $intent->forceFill([
                'status'=>FounderAdminSettingIntent::REJECTED,
                'executed_by'=>$founderId,
                'executed_at'=>now(),
            ])->save();
            return ['success'=>true,'status'=>'rejected','setting_key'=>$key,'intent_id'=>$intentId];
        }

        $result = $this->execution->execute(
            'admin_settings',
            'change_setting',
            fn (): array => $this->settings->change($key, $value),
            $requestId,
            [
                'entity_type'=>'founder_admin_setting_intent',
                'entity_id'=>$intentId,
                'requested_by'=>$founderId,
            ]
        );

        if ((bool) ($result['success'] ?? false)) {
            $intent->forceFill([
                'status'=>FounderAdminSettingIntent::EXECUTED,
                'executed_by'=>$founderId,
                'executed_at'=>now(),
            ])->save();
        }

        return $result;
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
