<?php

namespace Tests\Feature\Communication;

use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\Communication;
use App\Models\Invitation;
use App\Models\InvitationCode;
use App\Models\User;
use App\Services\Invitation\InvitationManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class InvitationEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_issue_queues_required_canonical_communication_without_direct_mail(): void
    {
        Mail::fake();
        Queue::fake();
        config(['invitation-management.system_issuer_user_id' => null]);

        $actor = User::factory()->create();
        $invitation = Invitation::query()->create([
            'email' => 'canonical-invite@example.test',
            'status' => 0,
        ]);

        $result = app(InvitationManagementService::class)->issue($invitation, (int) $actor->id);
        $code = InvitationCode::query()->findOrFail((int) $result['invitation_code_id']);

        $communication = Communication::query()
            ->where('source_type', 'invitation')
            ->where('source_id', (string) $invitation->id)
            ->firstOrFail();

        $this->assertSame('required', $communication->classification->value);
        $this->assertSame((string) $code->code, (string) data_get($communication->context_snapshot, 'code'));
        $this->assertSame((string) $code->expire_at->toISOString(), (string) data_get($communication->context_snapshot, 'expire_at'));
        $this->assertDatabaseHas('communication_recipients', [
            'communication_id' => $communication->id,
            'user_id' => null,
            'email' => $invitation->email,
            'status' => 'queued',
        ]);

        Mail::assertNothingSent();
        Queue::assertPushedOn('communications-critical', DeliverCommunicationRecipient::class);
    }

    public function test_replaying_same_issued_invitation_does_not_create_second_logical_communication(): void
    {
        Mail::fake();
        Queue::fake();
        config(['invitation-management.system_issuer_user_id' => null]);

        $actor = User::factory()->create();
        $invitation = Invitation::query()->create([
            'email' => 'dedupe-invite@example.test',
            'status' => 0,
        ]);

        app(InvitationManagementService::class)->issue($invitation, (int) $actor->id);
        app(InvitationManagementService::class)->issue($invitation->fresh(), (int) $actor->id);

        $this->assertSame(1, Communication::query()
            ->where('source_type', 'invitation')
            ->where('source_id', (string) $invitation->id)
            ->count());
    }
}
