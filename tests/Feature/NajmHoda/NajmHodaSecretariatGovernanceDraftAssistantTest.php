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
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatGovernanceDraftAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatGovernanceDraftAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_minute_and_adopted_resolution_preview_then_create_drafts_only(): void
    {
        [$actor, $office, $minute, $resolution] = $this->context('S7-GOV');
        $page = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatGovernanceDraftAssistant::class);

        $before = SecretariatRecord::query()->count();
        $minutePreview = $assistant->intercept(
            $actor,
            $page,
            "صورتجلسه رسمی آماده کن | صورتجلسه: {$minute->id}",
            9501,
        );
        $this->assertSame('awaiting_confirmation', $minutePreview['status']);
        $this->assertSame($before, SecretariatRecord::query()->count());

        $minuteSaved = $assistant->intercept($actor, $page, 'ذخیره صورتجلسه', 9501);
        $this->assertSame('draft_saved', $minuteSaved['status']);
        $minuteRecord = SecretariatRecord::query()
            ->where('source_type', 'meeting_minute')
            ->where('source_id', $minute->id)
            ->sole();
        $this->assertSame('draft', $minuteRecord->status);
        $this->assertNull($minuteRecord->registry_number);

        $beforeResolution = SecretariatRecord::query()->count();
        $resolutionPreview = $assistant->intercept(
            $actor,
            $page,
            "مصوبه رسمی آماده کن | مصوبه: {$resolution->id} | رکورد صورتجلسه: {$minuteRecord->id}",
            9502,
        );
        $this->assertSame('awaiting_confirmation', $resolutionPreview['status']);
        $this->assertSame($beforeResolution, SecretariatRecord::query()->count());

        $resolutionSaved = $assistant->intercept($actor, $page, 'ذخیره مصوبه', 9502);
        $this->assertSame('draft_saved', $resolutionSaved['status']);
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
    }

    public function test_unapproved_or_unadopted_sources_are_blocked(): void
    {
        [$actor, $office, $minute, $resolution] = $this->context('S7-GOV-BLOCK');
        $page = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatGovernanceDraftAssistant::class);

        $minute->forceFill(['status' => 'draft', 'approved_by' => null, 'approved_at' => null])->save();
        $blockedMinute = $assistant->intercept($actor, $page, "صورتجلسه رسمی آماده کن | صورتجلسه: {$minute->id}", 9503);
        $this->assertSame('blocked', $blockedMinute['status']);

        $resolution->forceFill(['status' => 'draft', 'adopted_at' => null])->save();
        $blockedResolution = $assistant->intercept($actor, $page, "مصوبه رسمی آماده کن | مصوبه: {$resolution->id}", 9504);
        $this->assertSame('blocked', $blockedResolution['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());
    }

    public function test_source_change_after_preview_is_rejected_as_stale(): void
    {
        [$actor, $office, $minute] = $this->context('S7-GOV-STALE');
        $page = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatGovernanceDraftAssistant::class);

        $preview = $assistant->intercept($actor, $page, "صورتجلسه رسمی آماده کن | صورتجلسه: {$minute->id}", 9505);
        $this->assertSame('awaiting_confirmation', $preview['status']);

        $minute->forceFill([
            'summary' => 'خلاصه پس از پیش‌نمایش تغییر کرد.',
            'updated_at' => now()->addSecond(),
        ])->save();

        $saved = $assistant->intercept($actor, $page, 'ذخیره صورتجلسه', 9505);
        $this->assertSame('stale_preview', $saved['status']);
        $this->assertSame(0, SecretariatRecord::query()->where('source_type', 'meeting_minute')->count());
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
            'title' => 'جلسه رسمی ' . $code,
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'approved',
            'summary' => 'خلاصه صورتجلسه تأییدشده',
            'minutes' => 'متن صورتجلسه تأییدشده و منبع حقیقت جلسه.',
            'approved_by' => $actor->id,
            'approved_at' => now()->subMinutes(30),
        ]);
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'پیشنهاد مصوب ' . $code,
            'summary' => 'خلاصه پیشنهاد مصوب',
            'description' => 'شرح کامل پیشنهاد مصوب',
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

    private function pageContext(User $actor, int $officeId): array
    {
        return app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.index',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_office',
                'resource_id' => $officeId,
                'title' => 'FORGED TITLE',
                'body' => 'FORGED BODY',
            ],
        ]);
    }
}
