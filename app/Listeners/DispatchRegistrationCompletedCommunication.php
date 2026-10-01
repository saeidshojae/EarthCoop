<?php

namespace App\Listeners;

use App\Events\RegistrationCompleted;
use App\Models\User;
use App\Services\Communication\CommunicationRuleEngine;
use App\Services\Communication\Context\WelcomeCommunicationContextBuilder;

final class DispatchRegistrationCompletedCommunication
{
    public function __construct(
        private readonly CommunicationRuleEngine $engine,
        private readonly WelcomeCommunicationContextBuilder $contextBuilder,
    ) {
    }

    public function handle(RegistrationCompleted $event): void
    {
        $user = User::query()->find($event->userId);
        if (! $user) {
            return;
        }

        $this->engine->handleEvent('registration.completed', [
            'user_id' => $user->id,
            'completed_at' => $event->completedAt,
            'locale' => $event->locale,
        ], $this->contextBuilder->build($user));
    }
}
