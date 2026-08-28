<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Announcement;
use App\Models\FounderAnnouncementDraft;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderAcceptanceLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_flows_from_draft_through_founder_approval_to_verified_canonical_outcome(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);
        config()->set('najm-hoda.runtime.autonomy.human_escalation.notify_admins', false);

        $title = 'Acceptance loop ' . uniqid('', true);
        $draftResult = app(FounderAnnouncementDraftService::class)->draft([
            'title' => $title,
            'content' => 'Founder acceptance loop canonical publication test.',
            'group_level' => '7',
            'should_pin' => false,
        ], 'acceptance-loop-announcement-' . $founder->id, $founder->id);

        $draft = FounderAnnouncementDraft::query()->findOrFail((int) $draftResult['draft_id']);
        $this->assertSame('draft', $draft->status);
        $this->assertFalse(Announcement::query()->where('title', $title)->exists());

        $service = app(FounderAnnouncementDecisionService::class);
        $prepared = $service->requestPublish($draft, $founder->id);

        $this->assertSame('awaiting_approval', $prepared['status']);
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);
        $this->assertFalse(Announcement::query()->where('title', $title)->exists());

        $executed = $service->decideAndExecute(
            $requestId,
            'approve',
            $founder->id,
            'Acceptance-loop test approval'
        );

        $this->assertTrue((bool) ($executed['success'] ?? false));
        $this->assertSame('executed', $executed['status']);
        $this->assertTrue((bool) data_get($executed, 'verification.verified'));
        $this->assertSame('verified', data_get($executed, 'verification.status'));

        $draft->refresh();
        $this->assertSame('published', $draft->status);
        $this->assertNotNull($draft->announcement_id);
        $this->assertDatabaseHas('announcements', [
            'id' => $draft->announcement_id,
            'title' => $title,
        ]);
    }

    public function test_unconfigured_outcome_verifier_never_claims_successful_verification(): void
    {
        $verification = app(\App\Services\NajmHoda\FounderOps\FounderActionOutcomeVerificationService::class)
            ->verify('governance', 'change_election_rules', ['rule' => 'example'], [
                'entity_type' => 'election',
                'entity_id' => 1,
            ]);

        $this->assertFalse((bool) $verification['verified']);
        $this->assertSame('not_configured', $verification['status']);
        $this->assertSame('no_canonical_outcome_verifier', $verification['reason']);
    }
}
