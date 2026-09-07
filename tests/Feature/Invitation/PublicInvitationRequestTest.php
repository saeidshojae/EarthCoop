<?php

namespace Tests\Feature\Invitation;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvitationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_invitation_request_form(): void
    {
        $this->get(route('invite'))
            ->assertOk();
    }

    public function test_guest_can_submit_invitation_request_for_admin_review(): void
    {
        $response = $this->post(route('invite.store'), [
            'email' => 'launch-candidate@example.com',
            'job' => 'Engineer',
            'message' => 'I would like to join EarthCoop.',
            'website' => '',
        ]);

        $response->assertRedirect(route('welcome'));

        $invitation = Invitation::where('email', 'launch-candidate@example.com')->firstOrFail();
        $this->assertSame(0, (int) $invitation->status);
    }

    public function test_welcome_page_keeps_request_invitation_call_to_action(): void
    {
        $welcome = file_get_contents(resource_path('views/welcome.blade.php'));

        $this->assertStringContainsString("route('invite')", $welcome);
        $this->assertStringContainsString('درخواست', $welcome);
    }
}
