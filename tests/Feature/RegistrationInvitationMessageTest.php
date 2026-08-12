<?php

namespace Tests\Feature;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class RegistrationInvitationMessageTest extends TestCase
{
    public function test_invitation_confirmation_is_hidden_when_invitation_system_is_disabled(): void
    {
        $html = view('auth.register', [
            'invitationRequired' => false,
            'invitationCode' => null,
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertStringNotContainsString('کد دعوت تایید شد', $html);
    }

    public function test_invitation_confirmation_is_shown_for_a_validated_invitation(): void
    {
        $html = view('auth.register', [
            'invitationRequired' => true,
            'invitationCode' => 'VALID-CODE',
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('کد دعوت تایید شد', $html);
        $this->assertStringContainsString('VALID-CODE', $html);
    }
}
