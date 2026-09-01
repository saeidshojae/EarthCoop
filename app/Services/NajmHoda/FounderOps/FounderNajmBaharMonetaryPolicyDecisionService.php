<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FounderNajmBaharMonetaryPolicyDecisionService
{
    private const ALLOWED_PARAMETERS = [
        'reputation_conversion_enabled',
        'reputation_to_gol_ratio',
        'auto_activation_enabled',
        'auto_activation_period',
        'auto_activation_amount_gol',
        'membership_fee_gol',
        'membership_operations_gol',
        'membership_insurance_gol',
        'membership_burn_gol',
        'idle_observation_period_days',
        'idle_observation_exempt_balance_gol',
        'idle_tax_enabled',
        'idle_tax_rate_bps',
    ];

    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected MonetaryPolicyService $policy
    ) {}

    /** @return array<string,mixed> */
    public function prepare(array $changes, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A monetary-policy change reason is required.']);
        }

        $unknown = array_values(array_diff(array_keys($changes), self::ALLOWED_PARAMETERS));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'parameters' => 'Unsupported monetary-policy parameters: ' . implode(', ', $unknown),
            ]);
        }

        $parameters = array_replace($this->baseline(), $changes);
        $parameters = $this->validateAndNormalize($parameters);

        $version = DB::transaction(function () use ($parameters, $reason): MonetaryPolicyVersion {
            $latest = MonetaryPolicyVersion::query()->orderByDesc('version')->lockForUpdate()->first();

            return MonetaryPolicyVersion::query()->create([
                'version' => ((int) ($latest?->version ?? 0)) + 1,
                'status' => 'draft',
                'parameters' => $parameters,
                'reason' => mb_substr($reason, 0, 2000),
                'effective_from' => null,
                'effective_until' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        });

        return [
            'success' => true,
            'status' => 'policy_draft_ready',
            'policy_version_id' => (int) $version->id,
            'version' => (int) $version->version,
            'policy_status' => (string) $version->status,
            'parameters' => $version->parameters,
        ];
    }

    /** @return array<string,mixed> */
    public function requestActivate(MonetaryPolicyVersion $version, int $requestedBy): array
    {
        if ((string) $version->status !== 'draft') {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'monetary_policy_version_not_draft'];
        }

        $this->validateAndNormalize((array) $version->parameters);

        return $this->requests->prepare('najm_bahar', 'change_monetary_policy', [
            'entity_type' => 'najm_bahar_monetary_policy_version',
            'entity_id' => (int) $version->id,
            'requested_by' => $requestedBy,
            'reason_code' => 'najm-bahar-monetary-policy-version-' . (int) $version->id,
            'source_event' => 'founder_ops_najm_bahar_monetary_policy',
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

        if ((string) data_get($pending, 'plan_item.domain') !== 'najm_bahar'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'change_monetary_policy'
            || (string) data_get($pending, 'context.entity_type') !== 'najm_bahar_monetary_policy_version') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $versionId = (int) data_get($pending, 'context.entity_id', 0);
        $version = $versionId > 0 ? MonetaryPolicyVersion::query()->find($versionId) : null;
        if (! $version) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'monetary_policy_version_not_found'];
        }
        if ((string) $version->status !== 'draft') {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'monetary_policy_version_not_draft'];
        }
        if (! User::query()->whereKey($founderId)->exists()) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'founder_user_not_found'];
        }

        $this->validateAndNormalize((array) $version->parameters);

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }

        if ($decision === 'reject') {
            $version->update([
                'status' => 'rejected',
                'approved_by' => $founderId,
                'approved_at' => now(),
            ]);

            return [
                'success' => true,
                'status' => 'rejected',
                'policy_version_id' => $versionId,
            ];
        }

        return $this->execution->execute(
            'najm_bahar',
            'change_monetary_policy',
            function () use ($versionId, $founderId): array {
                return DB::transaction(function () use ($versionId, $founderId): array {
                    $version = MonetaryPolicyVersion::query()->whereKey($versionId)->lockForUpdate()->firstOrFail();

                    if ((string) $version->status === 'active' && $version->approved_at) {
                        return [
                            'policy_version_id' => (int) $version->id,
                            'version' => (int) $version->version,
                            'approved_by' => (int) $version->approved_by,
                            'idempotent' => true,
                        ];
                    }
                    if ((string) $version->status !== 'draft') {
                        throw new \RuntimeException('Monetary policy version is no longer executable.');
                    }

                    $parameters = $this->validateAndNormalize((array) $version->parameters);
                    $version->forceFill([
                        'parameters' => $parameters,
                        'status' => 'active',
                        'effective_from' => now(),
                        'effective_until' => null,
                        'approved_by' => $founderId,
                        'approved_at' => now(),
                    ])->save();

                    return [
                        'policy_version_id' => (int) $version->id,
                        'version' => (int) $version->version,
                        'approved_by' => $founderId,
                        'idempotent' => false,
                    ];
                });
            },
            $requestId,
            [
                'entity_type' => 'najm_bahar_monetary_policy_version',
                'entity_id' => $versionId,
                'requested_by' => $founderId,
            ]
        );
    }

    /** @return array<string,mixed> */
    protected function baseline(): array
    {
        return [
            'reputation_conversion_enabled' => (bool) $this->policy->parameter('reputation_conversion_enabled', false),
            'reputation_to_gol_ratio' => (int) $this->policy->parameter('reputation_to_gol_ratio', 100),
            'auto_activation_enabled' => (bool) $this->policy->parameter('auto_activation_enabled', false),
            'auto_activation_period' => (string) $this->policy->parameter('auto_activation_period', 'monthly'),
            'auto_activation_amount_gol' => (int) $this->policy->parameter('auto_activation_amount_gol', 0),
            'membership_fee_gol' => (int) $this->policy->parameter('membership_fee_gol', BaharMoney::toGolFromBahar(12)),
            'membership_operations_gol' => (int) $this->policy->parameter('membership_operations_gol', BaharMoney::toGolFromBahar(6)),
            'membership_insurance_gol' => (int) $this->policy->parameter('membership_insurance_gol', BaharMoney::toGolFromBahar(3)),
            'membership_burn_gol' => (int) $this->policy->parameter('membership_burn_gol', BaharMoney::toGolFromBahar(3)),
            'idle_observation_period_days' => (int) $this->policy->parameter('idle_observation_period_days', 180),
            'idle_observation_exempt_balance_gol' => (int) $this->policy->parameter('idle_observation_exempt_balance_gol', 0),
            'idle_tax_enabled' => (bool) $this->policy->parameter('idle_tax_enabled', false),
            'idle_tax_rate_bps' => (int) $this->policy->parameter('idle_tax_rate_bps', 0),
        ];
    }

    /** @return array<string,mixed> */
    protected function validateAndNormalize(array $parameters): array
    {
        foreach (self::ALLOWED_PARAMETERS as $key) {
            if (! array_key_exists($key, $parameters)) {
                throw ValidationException::withMessages(['parameters' => "Missing monetary-policy parameter: {$key}"]);
            }
        }

        foreach (['reputation_conversion_enabled', 'auto_activation_enabled', 'idle_tax_enabled'] as $key) {
            if (! is_bool($parameters[$key])) {
                throw ValidationException::withMessages([$key => "{$key} must be boolean."]);
            }
        }

        foreach ([
            'reputation_to_gol_ratio',
            'auto_activation_amount_gol',
            'membership_fee_gol',
            'membership_operations_gol',
            'membership_insurance_gol',
            'membership_burn_gol',
            'idle_observation_period_days',
            'idle_observation_exempt_balance_gol',
            'idle_tax_rate_bps',
        ] as $key) {
            if (! is_int($parameters[$key])) {
                throw ValidationException::withMessages([$key => "{$key} must be an integer."]);
            }
        }

        if ($parameters['reputation_to_gol_ratio'] <= 0) {
            throw ValidationException::withMessages(['reputation_to_gol_ratio' => 'Reputation conversion ratio must be positive.']);
        }
        if (! in_array($parameters['auto_activation_period'], ['monthly', 'quarterly', 'yearly'], true)) {
            throw ValidationException::withMessages(['auto_activation_period' => 'Auto-activation period must be monthly, quarterly, or yearly.']);
        }
        if ($parameters['auto_activation_amount_gol'] < 0) {
            throw ValidationException::withMessages(['auto_activation_amount_gol' => 'Auto-activation amount cannot be negative.']);
        }
        if ($parameters['auto_activation_enabled'] && $parameters['auto_activation_amount_gol'] <= 0) {
            throw ValidationException::withMessages(['auto_activation_amount_gol' => 'Enabled auto-activation requires a positive amount.']);
        }

        foreach (['membership_fee_gol', 'membership_operations_gol', 'membership_insurance_gol', 'membership_burn_gol'] as $key) {
            if ($parameters[$key] < 0) {
                throw ValidationException::withMessages([$key => "{$key} cannot be negative."]);
            }
        }
        if ($parameters['membership_fee_gol'] <= 0) {
            throw ValidationException::withMessages(['membership_fee_gol' => 'Membership fee must be positive.']);
        }
        $split = $parameters['membership_operations_gol']
            + $parameters['membership_insurance_gol']
            + $parameters['membership_burn_gol'];
        if ($split !== $parameters['membership_fee_gol']) {
            throw ValidationException::withMessages(['membership_fee_gol' => 'Membership allocation must equal the total membership fee.']);
        }

        if ($parameters['idle_observation_period_days'] <= 0) {
            throw ValidationException::withMessages(['idle_observation_period_days' => 'Idle observation period must be positive.']);
        }
        if ($parameters['idle_observation_exempt_balance_gol'] < 0) {
            throw ValidationException::withMessages(['idle_observation_exempt_balance_gol' => 'Idle observation exemption cannot be negative.']);
        }
        if ($parameters['idle_tax_enabled'] || $parameters['idle_tax_rate_bps'] !== 0) {
            throw ValidationException::withMessages([
                'idle_tax_enabled' => 'Idle-tax collection is not executable until its canonical collection policy and service are approved.',
            ]);
        }

        return $parameters;
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
