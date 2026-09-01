<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Category;
use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService;
use App\Services\NajmHoda\FounderOps\FounderContentDecisionService;
use App\Services\NajmHoda\FounderOps\FounderEmailDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderCommunicationRejectReplayTest extends TestCase
{
    use RefreshDatabase;

    private int $founderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->founderId = (int) User::factory()->create()->id;
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$this->founderId]]);
        Cache::forget('najm_hoda:autonomy:approval:requests');
    }

    public function test_email_rejects_and_blocks_replay(): void
    {
        $draft = FounderEmailDraft::query()->create([
            'recipients' => ['person@example.com'],
            'subject' => 'UAT',
            'body' => 'Body',
            'status' => 'draft',
        ]);

        $service = app(FounderEmailDecisionService::class);
        $requested = $service->requestSend($draft, $this->founderId);
        $requestId = data_get($requested, 'approval_request.id');

        $this->assertSame('awaiting_approval', $requested['status'] ?? null);
        $this->assertNotEmpty($requestId);

        $rejected = $service->decideAndExecute((string) $requestId, 'reject', $this->founderId, 'UAT reject');
        $this->assertTrue((bool) ($rejected['success'] ?? false));
        $this->assertSame('rejected', $draft->fresh()->status);

        $replay = $service->decideAndExecute((string) $requestId, 'approve', $this->founderId);
        $this->assertFalse((bool) ($replay['success'] ?? true));
        $this->assertSame('invalid_request', $replay['status'] ?? null);
    }

    public function test_content_rejects_and_blocks_replay(): void
    {
        $category = Category::query()->create(['name' => 'مدیریت UAT']);
        $draft = FounderContentDraft::query()->create([
            'content_type' => 'blog_post',
            'group_id' => 1,
            'category_id' => (int) $category->id,
            'title' => 'UAT',
            'body' => 'Body',
            'status' => 'draft',
        ]);

        $service = app(FounderContentDecisionService::class);
        $requested = $service->requestPublish($draft, $this->founderId);
        $requestId = data_get($requested, 'approval_request.id');

        $this->assertSame('awaiting_approval', $requested['status'] ?? null);
        $this->assertNotEmpty($requestId);

        $rejected = $service->decideAndExecute((string) $requestId, 'reject', $this->founderId, 'UAT reject');
        $this->assertTrue((bool) ($rejected['success'] ?? false));
        $this->assertSame('rejected', $draft->fresh()->status);

        $replay = $service->decideAndExecute((string) $requestId, 'approve', $this->founderId);
        $this->assertFalse((bool) ($replay['success'] ?? true));
        $this->assertSame('invalid_request', $replay['status'] ?? null);
    }

    public function test_announcement_rejects_and_blocks_replay(): void
    {
        $draft = FounderAnnouncementDraft::query()->create([
            'title' => 'UAT',
            'content' => 'Announcement body',
            'group_level' => 'global',
            'should_pin' => false,
            'status' => 'draft',
            'reason_code' => 'announcement-uat-replay',
            'created_by' => $this->founderId,
        ]);

        $service = app(FounderAnnouncementDecisionService::class);
        $requested = $service->requestPublish($draft, $this->founderId);
        $requestId = data_get($requested, 'approval_request.id');

        $this->assertSame('awaiting_approval', $requested['status'] ?? null);
        $this->assertNotEmpty($requestId);

        $rejected = $service->decideAndExecute((string) $requestId, 'reject', $this->founderId, 'UAT reject');
        $this->assertTrue((bool) ($rejected['success'] ?? false));
        $this->assertSame('rejected', $draft->fresh()->status);

        $replay = $service->decideAndExecute((string) $requestId, 'approve', $this->founderId);
        $this->assertFalse((bool) ($replay['success'] ?? true));
        $this->assertSame('invalid_request', $replay['status'] ?? null);
    }
}
