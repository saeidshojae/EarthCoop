<?php

namespace App\Listeners;

use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;

class CaptureNajmHodaAuthLifecycle
{
    public function __construct(
        private RuntimeEventBus $runtimeEventBus,
        private NajmHodaDomainEventPolicyLinkService $policyLinkService
    ) {
    }

    public function handle(object $event): void
    {
        if (!config('najm-hoda.enabled', true)) {
            return;
        }

        if ($event instanceof Login) {
            $this->emit('najm_hoda.input.auth.service.lifecycle.login.succeeded', [
                'user_id' => $event->user?->id !== null ? (int) $event->user->id : null,
                'guard' => (string) $event->guard,
                'remember' => (bool) $event->remember,
                'scope' => 'auth',
                'risk' => 'low',
            ]);
            return;
        }

        if ($event instanceof Failed) {
            $this->emit('najm_hoda.input.auth.service.lifecycle.login.failed', [
                'user_id' => $event->user?->id !== null ? (int) $event->user->id : null,
                'guard' => (string) $event->guard,
                'has_user' => $event->user !== null,
                'scope' => 'auth',
                'risk' => 'medium',
            ]);
            return;
        }

        if ($event instanceof Logout) {
            $this->emit('najm_hoda.input.auth.service.lifecycle.logout.succeeded', [
                'user_id' => $event->user?->id !== null ? (int) $event->user->id : null,
                'guard' => (string) $event->guard,
                'scope' => 'auth',
                'risk' => 'low',
            ]);
            return;
        }

        if ($event instanceof Registered) {
            $this->emit('najm_hoda.input.auth.service.lifecycle.register.succeeded', [
                'user_id' => $event->user?->id !== null ? (int) $event->user->id : null,
                'scope' => 'auth',
                'risk' => 'low',
            ]);
            return;
        }

        if ($event instanceof PasswordReset) {
            $this->emit('najm_hoda.input.auth.service.lifecycle.password_reset.succeeded', [
                'user_id' => $event->user?->id !== null ? (int) $event->user->id : null,
                'scope' => 'auth',
                'risk' => 'low',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emit(string $event, array $payload): void
    {
        $this->runtimeEventBus->emit($event, $payload);
        $this->policyLinkService->ingest($event, $payload);
    }
}

