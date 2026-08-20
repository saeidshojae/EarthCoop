<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Invitation;
use App\Models\InvitationCode;
use App\Models\User;
use App\Services\Invitation\InvitationManagementService;
use App\Services\Invitation\InvitationSystemIssuerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class InvitationManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_issuer_defaults_to_null_instead_of_historical_magic_user(): void
    {
        config(['invitation-management.system_issuer_user_id' => null]);

        $this->assertNull(app(InvitationSystemIssuerResolver::class)->id());
    }

    public function test_configured_system_issuer_must_exist(): void
    {
        config(['invitation-management.system_issuer_user_id' => 987654321]);

        $this->expectException(RuntimeException::class);
        app(InvitationSystemIssuerResolver::class)->id();
    }

    public function test_configured_existing_system_issuer_is_resolved(): void
    {
        $user=User::factory()->create();
        config(['invitation-management.system_issuer_user_id' => $user->id]);

        $this->assertSame((int)$user->id, app(InvitationSystemIssuerResolver::class)->id());
    }

    public function test_issue_uses_nullable_system_issuer_and_marks_invitation_reviewed(): void
    {
        Mail::fake();
        config(['invitation-management.system_issuer_user_id' => null]);
        $actor=User::factory()->create();
        $invitation=Invitation::query()->create(['email'=>'candidate@example.test','status'=>0]);

        $result=app(InvitationManagementService::class)->issue($invitation,(int)$actor->id);

        $this->assertTrue((bool)($result['success']??false));
        $this->assertSame('issued',$result['status']??null);
        $this->assertDatabaseHas('invitations',['id'=>$invitation->id,'status'=>1,'reviewed_by'=>$actor->id]);
        $code=InvitationCode::query()->findOrFail((int)$result['invitation_code_id']);
        $this->assertNull($code->user_id);
        $this->assertFalse((bool)$code->used);
    }

    public function test_invalid_config_fails_before_invitation_state_changes(): void
    {
        Mail::fake();
        config(['invitation-management.system_issuer_user_id' => 987654321]);
        $actor=User::factory()->create();
        $invitation=Invitation::query()->create(['email'=>'candidate@example.test','status'=>0]);

        try {
            app(InvitationManagementService::class)->issue($invitation,(int)$actor->id);
            $this->fail('Expected invalid issuer configuration to fail closed.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('invitations',['id'=>$invitation->id,'status'=>0]);
            $this->assertSame(0,InvitationCode::query()->count());
        }
    }

    public function test_recommendation_flags_duplicate_pending_request(): void
    {
        Invitation::query()->create(['email'=>'same@example.test','status'=>0]);
        $candidate=Invitation::query()->create(['email'=>'same@example.test','status'=>0]);

        $result=app(InvitationManagementService::class)->recommend($candidate);

        $this->assertSame('review_duplicate_request',$result['recommendation']??null);
        $this->assertTrue((bool)data_get($result,'signals.duplicate_pending'));
    }
}
