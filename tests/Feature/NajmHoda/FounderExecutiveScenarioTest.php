<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Announcement;
use App\Models\FounderAnnouncementDraft;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderAcceptanceStatusService;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDraftService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderExecutiveScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepared_executive_work_moves_from_queue_to_founder_decision_to_verified_acceptance(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);
        config()->set('najm-hoda.runtime.autonomy.human_escalation.notify_admins', false);

        $title = 'Founder executive scenario ' . uniqid('', true);
        $draftResult = app(FounderAnnouncementDraftService::class)->draft([
            'title' => $title,
            'content' => 'Prepared by Najm Hoda for a founder executive acceptance scenario.',
            'group_level' => '7',
            'should_pin' => false,
        ], 'founder-executive-scenario-' . $founder->id, $founder->id);

        $draft = FounderAnnouncementDraft::query()->findOrFail((int) $draftResult['draft_id']);
        $this->assertSame('draft', $draft->status);
        $this->assertFalse(Announcement::query()->where('title', $title)->exists());

        $preparedQueue = app(FounderExecutiveWorkQueueService::class)->snapshot(24, 100);
        $preparedItem = collect($preparedQueue['items'] ?? [])->first(
            fn (array $item): bool => ($item['kind'] ?? null) === 'proposal'
                && ($item['domain'] ?? null) === 'notifications'
                && ($item['action'] ?? null) === 'publish_announcement'
                && (int) ($item['entity_id'] ?? 0) === (int) $draft->id
        );

        $this->assertNotNull($preparedItem);
        $this->assertSame('prepared', $preparedItem['status']);

        $decision = app(FounderAnnouncementDecisionService::class);
        $requested = $decision->requestPublish($draft, $founder->id);

        $this->assertSame('awaiting_approval', $requested['status']);
        $requestId = (string) data_get($requested, 'approval_request.id');
        $this->assertNotSame('', $requestId);
        $this->assertFalse(Announcement::query()->where('title', $title)->exists());

        $decisionQueue = app(FounderExecutiveWorkQueueService::class)->snapshot(24, 100);
        $approvalItem = collect($decisionQueue['items'] ?? [])->first(
            fn (array $item): bool => ($item['kind'] ?? null) === 'approval'
                && (string) ($item['approval_request_id'] ?? '') === $requestId
        );

        $this->assertNotNull($approvalItem);
        $this->assertSame('notifications', $approvalItem['domain']);
        $this->assertSame('publish_announcement', $approvalItem['action']);

        $executed = $decision->decideAndExecute(
            $requestId,
            'approve',
            $founder->id,
            'Founder executive scenario approval'
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

        $acceptance = app(FounderAcceptanceStatusService::class)->snapshot(100);
        $verified = collect($acceptance['items'] ?? [])->first(
            fn (array $item): bool => ($item['domain'] ?? null) === 'notifications'
                && ($item['action'] ?? null) === 'publish_announcement'
                && ($item['acceptance'] ?? null) === 'verified'
        );

        $this->assertNotNull($verified);

        $finalQueue = app(FounderExecutiveWorkQueueService::class)->snapshot(24, 100);
        $stillPrepared = collect($finalQueue['items'] ?? [])->contains(
            fn (array $item): bool => ($item['kind'] ?? null) === 'proposal'
                && ($item['domain'] ?? null) === 'notifications'
                && (int) ($item['entity_id'] ?? 0) === (int) $draft->id
        );
        $stillAwaitingApproval = collect($finalQueue['items'] ?? [])->contains(
            fn (array $item): bool => ($item['kind'] ?? null) === 'approval'
                && (string) ($item['approval_request_id'] ?? '') === $requestId
        );

        $this->assertFalse($stillPrepared);
        $this->assertFalse($stillAwaitingApproval);
    }
}
