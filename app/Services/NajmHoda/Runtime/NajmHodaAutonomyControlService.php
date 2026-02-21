<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaAutonomyControlService
{
    protected string $stateKey = 'najm_hoda:autonomy:control:state';
    protected string $overrideKey = 'najm_hoda:autonomy:control:override';
    protected string $killSwitchKey = 'najm_hoda:autonomy:control:kill_switch';

    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = Cache::get($this->stateKey, []);
        if (!is_array($state)) {
            $state = [];
        }

        return array_merge([
            'paused' => false,
            'paused_at' => null,
            'paused_until' => null,
            'reason' => null,
            'updated_by' => null,
            'updated_at' => null,
        ], $state);
    }

    public function isPaused(): bool
    {
        $state = $this->state();
        if (!(bool) ($state['paused'] ?? false)) {
            return false;
        }

        $pausedUntil = $state['paused_until'] ?? null;
        if (!is_string($pausedUntil) || trim($pausedUntil) === '') {
            return true;
        }

        try {
            $isActive = now()->lessThanOrEqualTo(\Carbon\CarbonImmutable::parse($pausedUntil));
            if (!$isActive) {
                $this->resume(null, 'pause_expired');
            }
            return $isActive;
        } catch (\Throwable) {
            return true;
        }
    }

    public function pause(?int $byUserId, ?string $reason = null, ?int $minutes = null): array
    {
        $state = [
            'paused' => true,
            'paused_at' => now()->toIso8601String(),
            'paused_until' => $minutes !== null && $minutes > 0 ? now()->addMinutes($minutes)->toIso8601String() : null,
            'reason' => $reason !== null ? trim($reason) : null,
            'updated_by' => $byUserId,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->stateKey, $state, now()->addDays(14));
        $this->eventBus->emit('najm_hoda.autonomy.control.paused', $state);

        return $state;
    }

    public function resume(?int $byUserId, ?string $reason = null): array
    {
        $state = [
            'paused' => false,
            'paused_at' => null,
            'paused_until' => null,
            'reason' => $reason !== null ? trim($reason) : null,
            'updated_by' => $byUserId,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->stateKey, $state, now()->addDays(14));
        $this->eventBus->emit('najm_hoda.autonomy.control.resumed', $state);

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function override(): array
    {
        $override = Cache::get($this->overrideKey, []);
        if (!is_array($override)) {
            $override = [];
        }

        return array_merge([
            'force_mode' => null,
            'blocked_actions' => [],
            'allow_apply_low_risk' => null,
            'reason' => null,
            'updated_by' => null,
            'updated_at' => null,
        ], $override);
    }

    /**
     * @param array<int, string> $blockedActions
     * @return array<string, mixed>
     */
    public function setOverride(?string $forceMode, array $blockedActions, ?bool $allowApplyLowRisk, ?int $byUserId, ?string $reason = null): array
    {
        $forceMode = $forceMode !== null ? strtolower(trim($forceMode)) : null;
        if (!in_array($forceMode, ['apply', 'propose', null], true)) {
            $forceMode = null;
        }

        $blockedActions = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $blockedActions
        ), static fn (string $v): bool => $v !== '')));

        $override = [
            'force_mode' => $forceMode,
            'blocked_actions' => $blockedActions,
            'allow_apply_low_risk' => $allowApplyLowRisk,
            'reason' => $reason !== null ? trim($reason) : null,
            'updated_by' => $byUserId,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->overrideKey, $override, now()->addDays(14));
        $this->eventBus->emit('najm_hoda.autonomy.control.override_set', $override);

        return $override;
    }

    /**
     * @return array<string, mixed>
     */
    public function clearOverride(?int $byUserId, ?string $reason = null): array
    {
        $override = [
            'force_mode' => null,
            'blocked_actions' => [],
            'allow_apply_low_risk' => null,
            'reason' => $reason !== null ? trim($reason) : null,
            'updated_by' => $byUserId,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->overrideKey, $override, now()->addDays(14));
        $this->eventBus->emit('najm_hoda.autonomy.control.override_cleared', $override);

        return $override;
    }

    /**
     * @return array<string, mixed>
     */
    public function killSwitchState(): array
    {
        $state = Cache::get($this->killSwitchKey, []);
        if (!is_array($state)) {
            $state = [];
        }

        return array_merge([
            'active' => false,
            'activated_at' => null,
            'active_until' => null,
            'reason' => null,
            'updated_by' => null,
            'updated_at' => null,
        ], $state);
    }

    public function isKillSwitchActive(): bool
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.kill_switch.enabled', true)) {
            return false;
        }

        $state = $this->killSwitchState();
        if (!(bool) ($state['active'] ?? false)) {
            return false;
        }

        $activeUntil = $state['active_until'] ?? null;
        if (!is_string($activeUntil) || trim($activeUntil) === '') {
            return true;
        }

        try {
            $isActive = now()->lessThanOrEqualTo(\Carbon\CarbonImmutable::parse($activeUntil));
            if (!$isActive) {
                $this->deactivateKillSwitch(null, 'kill_switch_expired');
            }
            return $isActive;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function activateKillSwitch(?int $byUserId, ?string $reason = null, ?int $minutes = null): array
    {
        $maxMinutes = max(1, (int) config('najm-hoda.runtime.autonomy.kill_switch.max_minutes', 10080));
        $effectiveMinutes = $minutes !== null ? max(1, min($minutes, $maxMinutes)) : null;

        $state = [
            'active' => true,
            'activated_at' => now()->toIso8601String(),
            'active_until' => $effectiveMinutes !== null ? now()->addMinutes($effectiveMinutes)->toIso8601String() : null,
            'reason' => $reason !== null ? trim($reason) : null,
            'updated_by' => $byUserId,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->killSwitchKey, $state, now()->addDays(14));
        $this->eventBus->emit('najm_hoda.autonomy.control.kill_switch.activated', $state);

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivateKillSwitch(?int $byUserId, ?string $reason = null): array
    {
        $state = [
            'active' => false,
            'activated_at' => null,
            'active_until' => null,
            'reason' => $reason !== null ? trim($reason) : null,
            'updated_by' => $byUserId,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->killSwitchKey, $state, now()->addDays(14));
        $this->eventBus->emit('najm_hoda.autonomy.control.kill_switch.deactivated', $state);

        return $state;
    }
}
