<?php

namespace App\Services\NajmHoda\Api;

use App\Models\Conversation;
use App\Models\NajmHodaAction;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaCrossModuleCapabilityOrchestratorService;
use App\Services\NajmHoda\Runtime\NajmHodaRuntimeAuthorityFactory;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class NajmHodaActionApplicationService
{
    public function __construct(
        private readonly NajmHodaCapabilityRegistry $capabilities,
        private readonly NajmHodaRuntimeAuthorityFactory $authorityFactory,
        private readonly NajmHodaCrossModuleCapabilityOrchestratorService $orchestrator,
    ) {
    }

    public function propose(User $actor, string $action, array $input, ?int $conversationId = null): NajmHodaAction
    {
        $contract = $this->capabilities->contract($action);
        $validation = $this->capabilities->validateInput($action, $input);

        if ($contract === null || ! (bool) ($validation['valid'] ?? false)) {
            throw new InvalidArgumentException((string) ($validation['reason'] ?? 'unknown_action_contract'));
        }

        if ($conversationId !== null) {
            Conversation::query()
                ->where('user_id', $actor->id)
                ->findOrFail($conversationId);
        }

        $normalized = $this->normalize($input);

        return NajmHodaAction::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'conversation_id' => $conversationId,
            'action' => $action,
            'contract_version' => (int) ($contract['version'] ?? 1),
            'risk' => (string) ($contract['risk'] ?? 'low'),
            'mode' => 'propose',
            'input' => $normalized,
            'input_hash' => hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'expected_output' => (array) ($contract['output'] ?? []),
            'status' => 'proposed',
            // Native apply is always an explicit two-step operation, even for low-risk capabilities.
            'consent_required' => true,
        ]);
    }

    public function getOwned(User $actor, string $publicId): NajmHodaAction
    {
        return NajmHodaAction::query()
            ->where('user_id', $actor->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    public function consent(User $actor, string $publicId): NajmHodaAction
    {
        return DB::transaction(function () use ($actor, $publicId): NajmHodaAction {
            $record = NajmHodaAction::query()
                ->where('user_id', $actor->id)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->status === 'consented') {
                return $record;
            }

            if ($record->status !== 'proposed') {
                throw new DomainException('invalid_action_state');
            }

            $record->forceFill([
                'status' => 'consented',
                'consented_at' => now(),
                'consent_evidence_id' => $record->consent_evidence_id ?: (string) Str::uuid(),
            ])->save();

            return $record->fresh();
        });
    }

    public function apply(User $actor, string $publicId, string $idempotencyKey): NajmHodaAction
    {
        $record = $this->getOwned($actor, $publicId);

        if ($record->status === 'applied' && hash_equals((string) $record->apply_idempotency_key, $idempotencyKey)) {
            return $record;
        }

        if ($record->status !== 'consented' || ! $record->consent_evidence_id) {
            throw new DomainException('consent_required');
        }

        try {
            $authority = $this->authorityFactory->apply(
                $actor,
                (string) $record->action,
                (array) $record->input,
                'mobile_api',
                (string) $record->consent_evidence_id,
            );

            $result = $this->orchestrator->orchestrate([[
                'action' => (string) $record->action,
                'priority' => 'stability',
                'reason' => 'mobile_consented_action',
                'input' => (array) $record->input,
                'preconditions' => ['kill_switch_off'],
            ]], [], $authority->allowApply, $authority->actorId);

            $executed = collect((array) ($result['steps'] ?? []))
                ->contains(fn ($step) => is_array($step) && (string) ($step['status'] ?? '') === 'executed');

            $status = $executed ? 'applied' : 'blocked';
            $errorCode = $executed
                ? null
                : (string) ($result['reason'] ?? data_get($result, 'steps.0.reason', 'apply_not_executed'));

            $record->forceFill([
                'status' => $status,
                'mode' => 'apply',
                'apply_idempotency_key' => $idempotencyKey,
                'applied_at' => $executed ? now() : null,
                'evidence_id' => $record->evidence_id ?: (string) Str::uuid(),
                'runtime_run_id' => $result['run_id'] ?? null,
                'result' => $result,
                'error_code' => $errorCode,
            ])->save();

            return $record->fresh();
        } catch (AuthorizationException $exception) {
            $reason = trim($exception->getMessage()) ?: 'resource_not_accessible';
            $this->markBlocked($record, $idempotencyKey, $reason, [
                'executed' => false,
                'status' => 'blocked',
                'reason' => $reason,
            ]);

            throw $exception;
        } catch (InvalidArgumentException $exception) {
            $reason = trim($exception->getMessage()) ?: 'action_not_allowed';
            $this->markBlocked($record, $idempotencyKey, $reason, [
                'executed' => false,
                'status' => 'blocked',
                'reason' => $reason,
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $record->forceFill([
                'status' => 'failed',
                'mode' => 'apply',
                'apply_idempotency_key' => $idempotencyKey,
                'evidence_id' => $record->evidence_id ?: (string) Str::uuid(),
                'result' => ['executed' => false, 'status' => 'failed'],
                'error_code' => 'runtime_failure',
            ])->save();

            throw $exception;
        }
    }

    private function markBlocked(NajmHodaAction $record, string $idempotencyKey, string $reason, array $result): void
    {
        $record->forceFill([
            'status' => 'blocked',
            'mode' => 'apply',
            'apply_idempotency_key' => $idempotencyKey,
            'evidence_id' => $record->evidence_id ?: (string) Str::uuid(),
            'result' => $result,
            'error_code' => $reason,
        ])->save();
    }

    private function normalize(array $input): array
    {
        ksort($input);
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = $this->normalize($value);
            }
        }

        return $input;
    }
}
