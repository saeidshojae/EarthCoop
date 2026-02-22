<?php

namespace App\Services;

use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Auth;
use Throwable;

class GoogleLoginService
{
    public function login(array $userData)
    {
        $context = [
            'scope' => 'auth',
            'risk' => 'low',
            'has_email' => !empty($userData['email']),
        ];
        $this->emitRuntime('najm_hoda.input.auth.service.google_login.requested', $context);

        try {
            $email = (string) ($userData['email'] ?? '');
            if ($email === '') {
                $this->emitRuntime('najm_hoda.input.auth.service.google_login.rejected', array_merge($context, [
                    'reason' => 'missing_email',
                    'risk' => 'medium',
                ]));
                return null;
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->emitRuntime('najm_hoda.input.auth.service.google_login.rejected', array_merge($context, [
                    'reason' => 'user_not_found',
                    'risk' => 'medium',
                ]));
                return null;
            }

            Auth::login($user);
            $this->emitRuntime('najm_hoda.input.auth.service.google_login.succeeded', array_merge($context, [
                'user_id' => (int) $user->id,
            ]));

            return $user;
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.auth.service.google_login.failed', array_merge($context, [
                'error' => $e->getMessage(),
                'risk' => 'high',
            ]));

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);

            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // no-op
        }
    }
}
