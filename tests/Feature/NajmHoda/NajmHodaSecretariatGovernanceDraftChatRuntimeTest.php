<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatGovernanceDraftChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_creates_minute_and_resolution_drafts_only_after_explicit_confirmation(): void
    {
        config(['najm-hoda.enabled' => true]);
        [$actor, $office, $minute, $resolution] = $this->context('S7-GOV-RT');
        $this->actingAs($actor);

        $page = [
            'route_name' => 'secretariat.index',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
            'title' => 'BROWSER FORGED TITLE',
            'body' => 'BROWSER FORGED BODY',
        ];

        $before = SecretariatRecord::query()->count();
        $minutePreview = $this->postJson('/api/najm-hoda/chat', [
            'message' => "صورتجلسه رسمی آماده کن | صورتجلسه: {$minute->id}",
            'context' => ['page' => $page],
        ]);
        $minutePreview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_governance_draft');
        $this->assertSame($before, SecretariatRecord::query()->count());
        $this->assertStringNotContainsString('BROWSER FORGED BODY', (string) $minutePreview->json('message'));

        $minuteSaved = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره صورتجلسه',
            'conversation_id' => (int) $minutePreview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);
        $minuteSaved->assertOk()->assertJsonPath('agent', 'secretariat_governance_draft');
        $minuteRecord = SecretariatRecord::query()
            ->where('source_type', 'meeting_minute')
            ->where('source_id', $minute->id)
            ->sole();
        $this->assertSame('draft', $minuteRecord->status);
        $this->assertNull($minuteRecord->registry_number);

        $beforeResolution = SecretariatRecord::query()->count();
        $resolutionPreview = $this->postJson('/api/najm-hoda/chat', [
            'message' => "مصوبه رسمی آماده کن | مصوبه: {$resolution->id} | رکورد صورتجلسه: {$minuteRecord->id}",
            'context' => ['page' => $page],
        ]);
        $resolutionPreview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_governance_draft');
        $this->assertSame($beforeResolution, SecretariatRecord::query()->count());

        $resolutionSaved = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره مصوبه',
            'conversation_id' => (int) $resolutionPreview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);
        $resolutionSaved->assertOk()->assertJsonPath('agent', 'secretariat_governance_draft');

        $resolutionRecord = SecretariatRecord::query()
            ->where('source_type', 'governance_resolution')
            ->where('source_id', $resolution->id)
            ->sole();
        $this->assertSame('draft', $resolutionRecord->status);
        $this->assertNull($resolutionRecord->registry_number);
        $this->assertDatabaseHas('secretariat_relations', [
            'source_record_id' => $resolutionRecord->id,
            'target_record_id' => $minuteRecord->id,
            'relation_type' => 'decision_of',
        ]);
        $this->assertSame(0, $resolutionRecord->dispatches()->count());
    }

    private function context(string $code): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => $code . ' Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $session = GroupSession::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'title' => 'جلسه رسمی runtime',
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'approved',
            'summary' => 'خلاصه صورتجلسه runtime',
            'minutes' => 'متن صورتجلسه runtime',
            'approved_by' => $actor->id,
            'approved_at' => now()->subMinutes(30),
        ]);
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'پیشنهاد مصوب runtime',
            'summary' => 'خلاصه پیشنهاد',
            'description' => 'شرح پیشنهاد',
            'status' => 'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'adopted_by' => $actor->id,
            'type' => 'general',
            'status' => 'adopted',
            'effect_status' => 'none',
            'adopted_at' => now()->subMinutes(20),
            'effective_at' => now()->subMinutes(20),
        ]);

        return [$actor, $office, $minute, $resolution];
    }
}
