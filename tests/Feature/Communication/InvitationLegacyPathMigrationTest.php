<?php

namespace Tests\Feature\Communication;

use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\Communication;
use App\Models\Invitation;
use App\Models\InvitationCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class InvitationLegacyPathMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_approval_uses_canonical_invitation_issue_path_without_direct_mail(): void
    {
        Mail::fake();
        Queue::fake();
        config(['invitation-management.system_issuer_user_id' => null]);

        $admin = User::factory()->create(['is_admin' => true]);
        $invitation = Invitation::query()->create([
            'email' => 'admin-approved@example.test',
            'status' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invitation_requests.approve', $invitation))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => 1,
            'reviewed_by' => $admin->id,
        ]);

        $code = InvitationCode::query()->latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-F0-9]{6}$/', (string) $code->code);

        $communication = Communication::query()
            ->where('source_type', 'invitation')
            ->where('source_id', (string) $invitation->id)
            ->firstOrFail();

        $this->assertSame('required', $communication->classification->value);
        Mail::assertNothingSent();
        Queue::assertPushedOn('communications-critical', DeliverCommunicationRecipient::class);
    }

    public function test_member_profile_invitation_preserves_owner_and_ten_character_code_but_uses_operational_canonical_delivery(): void
    {
        Mail::fake();
        Queue::fake();

        $member = User::factory()->create();
        $settings = Setting::singleton();
        $settings->expire_invation_time = 24;
        $settings->save();

        $this->actingAs($member)
            ->post(route('profile.send.invitation'), ['invite_email' => 'friend@example.test'])
            ->assertSessionHas('success');

        $code = InvitationCode::query()->latest('id')->firstOrFail();
        $this->assertSame($member->id, $code->user_id);
        $this->assertSame(10, mb_strlen((string) $code->code));

        $communication = Communication::query()
            ->where('source_type', 'member_invitation_code')
            ->where('source_id', (string) $code->id)
            ->firstOrFail();

        $this->assertSame('operational', $communication->classification->value);
        $this->assertDatabaseHas('communication_recipients', [
            'communication_id' => $communication->id,
            'user_id' => null,
            'email' => 'friend@example.test',
            'status' => 'queued',
        ]);

        Mail::assertNothingSent();
        Queue::assertPushed(DeliverCommunicationRecipient::class);
    }
}
